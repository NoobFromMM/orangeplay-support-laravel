<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Payments\DuplicatePaymentDetector;
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
        DuplicatePaymentDetector $duplicateDetector,
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

                // Before FAQ, check for payment email attachment
                $emailAttached = false;
                if ($normalized['text']) {
                    $emailAttached = $this->tryAttachEmail($customer, $conversation, $normalized, $botService);
                }

                if ($emailAttached) {
                    $conversationService->setStatus($conversation, 'pending_review');
                } elseif ($matchedEntry) {
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
                    $duplicateDetector,
                    $botService,
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
        DuplicatePaymentDetector $duplicateDetector,
        TelegramBotService $botService,
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
                    $existing = $duplicateDetector->findDuplicate(
                        $workerResult['provider'] ?? null,
                        $workerResult['transaction_id'] ?? null,
                    );

                    if ($existing) {
                        $this->handleDuplicatePayment(
                            $customer, $conversation, $normalized,
                            $latestImageMessage, $existing, $workerResult, $botService,
                        );
                    } else {
                        $paymentCase = $paymentScreenshotService->processImageMessage($latestImageMessage, $workerResult);

                        if ($paymentCase) {
                            $this->maybeAskForEmail($customer, $conversation, $normalized, $paymentCase, $botService);
                        }
                    }
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

    protected function maybeAskForEmail(
        $customer,
        $conversation,
        array $normalized,
        \App\Models\PaymentCase $paymentCase,
        TelegramBotService $botService,
    ): void {
        $customerEmail = $paymentCase->customer_email;

        if (! empty($customerEmail)) {
            return;
        }

        $chatId = $normalized['platform_user_id'];
        $messageText = 'Payment Screenshot ရရှိပါပြီရှင့်။ ဘယ် Orange Play account Email ကို သက်တမ်းတိုးချင်တာလဲ ပို့ပေးပါရှင့်။';

        $botService->sendMessage($chatId, $messageText);

        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => $normalized['platform'],
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => $messageText,
            'metadata' => [
                'source' => 'telegram_bot',
                'event' => 'ask_email_after_payment',
                'payment_case_id' => $paymentCase->id,
            ],
        ]);

        $paymentCase->update(['status' => 'needs_email']);
    }

    protected function handleDuplicatePayment(
        $customer,
        $conversation,
        array $normalized,
        $imageMessage,
        \App\Models\PaymentCase $existing,
        array $workerResult,
        TelegramBotService $botService,
    ): void {
        $replyTexts = [
            'needs_email' => 'ဒီငွေလွှဲ Screenshot ကို လက်ခံထားပြီးပါပြီရှင့်။ သက်တမ်းတိုးမယ့် Orange Play account Email ကို ပို့ပေးပါရှင့်။',
            'pending_review' => 'ဒီငွေလွှဲ Screenshot ကို လက်ခံထားပြီး Admin Team စစ်ဆေးနေပါပြီရှင့်။',
            'approved' => 'ဒီငွေလွှဲ Screenshot ကို စစ်ဆေးအတည်ပြုပြီးသား ဖြစ်ပါတယ်ရှင့်။',
            'rejected' => 'ဒီငွေလွှဲ Screenshot ကို အတည်မပြုနိုင်ခဲ့ပါရှင့်။ မှန်ကန်တဲ့ Screenshot အသစ်ကို ပြန်ပို့ပေးပါရှင့်။',
        ];

        $status = $existing->status;
        $replyText = $replyTexts[$status] ?? 'ဒီငွေလွှဲ Screenshot ကို လက်ခံထားပြီးပါပြီရှင့်။';

        $chatId = $normalized['platform_user_id'];
        $botService->sendMessage($chatId, $replyText);

        // Save duplicate notice timeline message
        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => $normalized['platform'],
            'direction' => 'system',
            'sender_type' => 'system',
            'message_type' => 'payment_duplicate_notice',
            'text' => 'Duplicate payment screenshot detected',
            'metadata' => [
                'duplicate_of_payment_case_id' => $existing->id,
                'duplicate_status' => $existing->status,
                'provider' => $workerResult['provider'] ?? null,
                'transaction_id' => $workerResult['transaction_id'] ?? null,
                'image_message_id' => $imageMessage->id,
            ],
        ]);

        // Save outbound bot message
        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => $normalized['platform'],
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => $replyText,
            'metadata' => [
                'source' => 'telegram_bot',
                'event' => 'payment_duplicate_detected',
                'duplicate_of_payment_case_id' => $existing->id,
                'duplicate_status' => $existing->status,
                'transaction_id' => $workerResult['transaction_id'] ?? null,
            ],
        ]);
    }

    protected function tryAttachEmail(
        $customer,
        $conversation,
        array $normalized,
        TelegramBotService $botService,
    ): bool {
        $email = $this->extractEmail($normalized['text'] ?? '');

        if (! $email) {
            return false;
        }

        $openCase = \App\Models\PaymentCase::where('customer_id', $customer->id)
            ->whereIn('status', ['needs_email', 'pending_review'])
            ->whereNull('customer_email')
            ->latest()
            ->first();

        if (! $openCase) {
            return false;
        }

        $newStatus = $openCase->status === 'needs_email' ? 'pending_review' : $openCase->status;

        $openCase->update([
            'customer_email' => $email,
            'status' => $newStatus,
        ]);

        $chatId = $normalized['platform_user_id'];
        $messageText = 'Email ရရှိပါပြီရှင့်။ Admin Team မှ ငွေလွှဲအချက်အလက်ကို စစ်ဆေးပြီး ပြန်လည်ဆက်သွယ်ပေးပါမယ်။';

        $botService->sendMessage($chatId, $messageText);

        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => $normalized['platform'],
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => $messageText,
            'metadata' => [
                'source' => 'telegram_bot',
                'event' => 'payment_email_received',
                'payment_case_id' => $openCase->id,
                'customer_email' => $email,
            ],
        ]);

        return true;
    }

    protected function extractEmail(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        $trimmed = trim($text);

        if (preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $trimmed)) {
            return mb_strtolower($trimmed);
        }

        return null;
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
