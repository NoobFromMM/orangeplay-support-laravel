<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentCheckClient;
use App\Services\Payments\PaymentScreenshotService;
use App\Services\Support\ConversationService;
use App\Services\Support\FaqMatcher;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        TelegramBotService $botService,
        PaymentCheckClient $paymentCheckClient,
        PaymentScreenshotService $paymentScreenshotService,
    ): JsonResponse {
        $update = $request->all();

        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => $this->detectEventType($update),
            'external_event_id' => (string) ($update['update_id'] ?? ''),
            'external_user_id' => $this->extractUserId($update),
            'payload' => $update,
            'status' => 'received',
            'attempts' => 1,
        ]);

        try {
            $normalized = $normalizer->normalize($update);

            $customer = $conversationService->findOrCreateCustomer(
                $normalized['platform'],
                $normalized['platform_user_id'],
                [
                    'display_name' => $normalized['display_name'],
                    'username' => $normalized['username'],
                ]
            );

            $conversation = $conversationService->findOrCreateConversation($customer);

            $conversationService->saveInboundMessage($conversation, $normalized);

            if ($normalized['message_type'] === 'text') {
                $matchedEntry = $faqMatcher->match($normalized['text']);

                if ($matchedEntry) {
                    $replyText = $matchedEntry->answer_text;

                    $chatId = $normalized['platform_user_id'];
                    $botService->sendMessage($chatId, $replyText);

                    $conversationService->saveOutboundMessage(
                        $conversation,
                        $normalized['platform'],
                        $replyText
                    );

                    $conversationService->setStatus($conversation, 'resolved');
                } else {
                    $conversationService->setStatus($conversation, 'Needs Reply');
                }
            } elseif ($normalized['message_type'] === 'image') {
                $conversationService->setStatus($conversation, 'Needs Reply');

                $this->tryPaymentCheck(
                    $customer,
                    $conversation,
                    $normalized,
                    $paymentCheckClient,
                    $paymentScreenshotService,
                );
            } else {
                $conversationService->setStatus($conversation, 'Needs Reply');
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $event->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            Log::error('Telegram webhook processing failed', [
                'webhook_event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    protected function detectEventType(array $update): string
    {
        if (isset($update['message'])) {
            return 'message';
        }
        if (isset($update['edited_message'])) {
            return 'edited_message';
        }
        if (isset($update['callback_query'])) {
            return 'callback_query';
        }

        return 'unknown';
    }

    protected function extractUserId(array $update): string
    {
        $container = $update['message']
            ?? $update['edited_message']
            ?? $update['callback_query']
            ?? [];

        return (string) ($container['from']['id'] ?? '');
    }

    protected function tryPaymentCheck(
        $customer,
        $conversation,
        array $normalized,
        PaymentCheckClient $paymentCheckClient,
        PaymentScreenshotService $paymentScreenshotService,
    ): void {
        $latestImageMessage = null;
        $checkedAt = now()->toIso8601String();

        try {
            $fileId = $normalized['metadata']['telegram_file_id'] ?? null;

            if (empty($fileId)) {
                return;
            }

            $imageBytes = $this->downloadTelegramFile($fileId);

            $workerResult = $paymentCheckClient->checkImageBytes($imageBytes, [
                'platform' => $normalized['platform'],
                'platform_user_id' => $normalized['platform_user_id'],
                'message_id' => $normalized['raw_payload']['message']['message_id'] ?? null,
                'telegram_file_id' => $fileId,
            ]);

            $latestImageMessage = \App\Models\Message::where('conversation_id', $conversation->id)
                ->where('message_type', 'image')
                ->latest()
                ->first();

            if ($latestImageMessage) {
                $latestImageMessage->metadata = array_merge(
                    $latestImageMessage->metadata ?? [],
                    ['payment_check' => $this->buildPaymentCheckMeta($workerResult, $checkedAt)]
                );
                $latestImageMessage->save();
            }

            if (! empty($workerResult['is_payment'])) {
                if ($latestImageMessage) {
                    $paymentScreenshotService->processImageMessage($latestImageMessage, $workerResult);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Payment check failed for image', [
                'error' => $e->getMessage(),
            ]);

            if ($latestImageMessage) {
                $latestImageMessage->metadata = array_merge(
                    $latestImageMessage->metadata ?? [],
                    ['payment_check' => [
                        'checked' => true,
                        'ok' => false,
                        'is_payment' => false,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                        'checked_at' => $checkedAt,
                    ]]
                );
                $latestImageMessage->save();
            }
        }
    }

    protected function buildPaymentCheckMeta(array $workerResult, string $checkedAt): array
    {
        return [
            'checked' => true,
            'ok' => $workerResult['ok'] ?? false,
            'is_payment' => (bool) ($workerResult['is_payment'] ?? false),
            'provider' => $workerResult['provider'] ?? null,
            'transaction_id' => $workerResult['transaction_id'] ?? null,
            'amount' => $workerResult['amount'] ?? null,
            'confidence' => $workerResult['confidence'] ?? null,
            'reason' => isset($workerResult['reason']) ? mb_substr((string) $workerResult['reason'], 0, 500) : null,
            'error' => isset($workerResult['error']) ? mb_substr((string) $workerResult['error'], 0, 500) : null,
            'checked_at' => $checkedAt,
        ];
    }

    protected function downloadTelegramFile(string $fileId): string
    {
        $token = env('TELEGRAM_BOT_TOKEN');

        $fileResponse = Http::timeout(15)->get("https://api.telegram.org/bot{$token}/getFile", [
            'file_id' => $fileId,
        ]);

        if (! $fileResponse->successful() || ! ($fileResponse->json('ok') ?? false)) {
            throw new \RuntimeException('Failed to get Telegram file info');
        }

        $filePath = $fileResponse->json('result.file_path');

        if (empty($filePath)) {
            throw new \RuntimeException('File path missing in Telegram response');
        }

        $downloadResponse = Http::timeout(30)->get("https://api.telegram.org/file/bot{$token}/{$filePath}");

        if (! $downloadResponse->successful()) {
            throw new \RuntimeException('Failed to download Telegram file');
        }

        return $downloadResponse->body();
    }
}
