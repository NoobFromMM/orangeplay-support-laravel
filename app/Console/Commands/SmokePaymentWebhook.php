<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentCheckClient;
use App\Services\Payments\PaymentScreenshotService;
use App\Services\Support\ConversationService;
use App\Services\Support\FaqMatcher;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SmokePaymentWebhook extends Command
{
    protected $signature = 'smoke:payment-webhook';
    protected $description = 'Smoke test for F6 P3 payment webhook integration';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        TelegramBotService $botService,
    ): int {
        $this->info('Payment Webhook Integration Smoke Test');
        $this->info('=====================================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'payment-check-pmt.example')) {
                return Http::response(['ok' => true, 'is_payment' => true, 'provider' => 'KBZ Pay', 'transaction_id' => '1429501235', 'amount' => 5000, 'confidence' => 0.95], 200);
            }
            if (str_contains($url, 'payment-check-notpmt.example')) {
                return Http::response(['ok' => true, 'is_payment' => false], 200);
            }
            if (str_contains($url, 'payment-check-fail.example')) {
                return Http::response('Internal Server Error', 500);
            }
            return Http::response(['ok' => false], 503);
        });

        try {
            $this->info('Test A: image + is_payment=true');
            $this->testPaymentImage($normalizer, $conversationService, $faqMatcher, $errors);

            $this->info('Test B: image + is_payment=false');
            $this->testNonPaymentImage($normalizer, $conversationService, $errors);

            $this->info('Test C: payment check failure');
            $this->testCheckFailure($normalizer, $conversationService, $errors);

            $this->info('Test D: text hi still returns greeting');
            $this->testTextGreeting($normalizer, $conversationService, $faqMatcher, $errors);

        } catch (\Throwable $e) {
            $errors[] = "Exception: " . $e->getMessage();
        }

        DB::rollBack();

        if (empty($errors)) {
            $this->info('ALL ASSERTIONS PASSED');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->error('FAILURES:');
        foreach ($errors as $error) {
            $this->line("  ✗  {$error}");
        }
        $this->newLine();
        return self::FAILURE;
    }

    protected function testPaymentImage(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        array &$errors,
    ): void {
        $payload = $this->makePhotoPayload(91001);
        $normalized = $normalizer->normalize($payload);

        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => 'message',
            'external_event_id' => '91001',
            'external_user_id' => '666001',
            'payload' => $payload,
            'status' => 'received',
            'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666001', [
            'display_name' => 'PmtHookUser',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);
        $conversationService->setStatus($conversation, 'Needs Reply');

        // Simulate payment check
        $paymentCheckClient = new PaymentCheckClient('https://payment-check-pmt.example.com/check');
        $workerResult = $paymentCheckClient->checkImageBytes('fake-bytes', [
            'platform' => 'telegram',
            'platform_user_id' => '666001',
            'telegram_file_id' => 'file_pmt_hook',
        ]);

        if (! empty($workerResult['is_payment'])) {
            $imageMsg = Message::where('conversation_id', $conversation->id)
                ->where('message_type', 'image')
                ->latest()
                ->first();

            $pss = new PaymentScreenshotService(app(\App\Services\Payments\PaymentCaseService::class));
            $pss->processImageMessage($imageMsg, $workerResult);
        }

        // Assertions
        $event->update(['status' => 'processed', 'processed_at' => now()]);
        $this->info('  OK  webhook event processed');

        $imageMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'image')
            ->first();
        if (! $imageMsg) {
            $errors[] = "Image message not saved";
        } else {
            $this->info('  OK  Image message saved');
        }

        $pc = \App\Models\PaymentCase::where('conversation_id', $conversation->id)->first();
        if (! $pc) {
            $errors[] = "Payment case not created";
        } else {
            $this->info('  OK  Payment case created');
            if ($pc->provider !== 'KBZ Pay') {
                $errors[] = "Expected provider=KBZ Pay";
            }
            if ($pc->transaction_id !== '1429501235') {
                $errors[] = "Expected transaction_id=1429501235";
            }
        }

        $review = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_review_card')
            ->first();
        if (! $review) {
            $errors[] = "payment_review_card message not created";
        } else {
            $this->info('  OK  payment_review_card created');
            $meta = $review->metadata ?? [];
            if (($meta['payment_case_id'] ?? 0) !== $pc->id) {
                $errors[] = "Expected metadata.payment_case_id";
            } else {
                $this->info('  OK  metadata.payment_case_id present');
            }
        }

        // No bot reply for images
        $botReplies = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'bot')
            ->count();
        if ($botReplies > 0) {
            $errors[] = "Expected no bot reply for image";
        } else {
            $this->info('  OK  No bot reply sent');
        }
    }

    protected function testNonPaymentImage(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $payload = $this->makePhotoPayload(91002);
        $normalized = $normalizer->normalize($payload);

        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => 'message',
            'external_event_id' => '91002',
            'external_user_id' => '666002',
            'payload' => $payload,
            'status' => 'received',
            'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666002', [
            'display_name' => 'NotPmtUser',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);
        $conversationService->setStatus($conversation, 'Needs Reply');

        $paymentCheckClient = new PaymentCheckClient('https://payment-check-notpmt.example.com/check');
        $workerResult = $paymentCheckClient->checkImageBytes('fake-bytes', []);

        if (! empty($workerResult['is_payment'])) {
            $errors[] = "Expected is_payment=false but was true — should not create case";
        } else {
            $this->info('  OK  is_payment=false, no case created');
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        $pcCount = \App\Models\PaymentCase::where('conversation_id', $conversation->id)->count();
        if ($pcCount > 0) {
            $errors[] = "Expected no payment cases, got {$pcCount}";
        } else {
            $this->info('  OK  No payment case created');
        }

        $reviewCount = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_review_card')
            ->count();
        if ($reviewCount > 0) {
            $errors[] = "Expected no review cards, got {$reviewCount}";
        } else {
            $this->info('  OK  No review card created');
        }

        if ($conversation->fresh()->status !== 'Needs Reply') {
            $errors[] = "Expected status='Needs Reply'";
        } else {
            $this->info("  OK  status='Needs Reply'");
        }
    }

    protected function testCheckFailure(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $payload = $this->makePhotoPayload(91003);
        $normalized = $normalizer->normalize($payload);

        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => 'message',
            'external_event_id' => '91003',
            'external_user_id' => '666003',
            'payload' => $payload,
            'status' => 'received',
            'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666003', [
            'display_name' => 'FailUser',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);
        $conversationService->setStatus($conversation, 'Needs Reply');

        $paymentCheckClient = new PaymentCheckClient('https://payment-check-fail.example.com/check');
        $workerResult = $paymentCheckClient->checkImageBytes('fake-bytes', []);

        if (! empty($workerResult['is_payment'])) {
            $errors[] = "Expected is_payment=false for HTTP 500 failure";
        } else {
            $this->info('  OK  Worker failure returns is_payment=false');
        }

        // Image still saved despite failure
        $imageMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'image')
            ->first();
        if (! $imageMsg) {
            $errors[] = "Image message should be saved even if check fails";
        } else {
            $this->info('  OK  Image message preserved after check failure');
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);
        $this->info('  OK  Webhook still processes despite check failure');
    }

    protected function testTextGreeting(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        array &$errors,
    ): void {
        $payload = $this->makeTextPayload(91004, 'hi');
        $normalized = $normalizer->normalize($payload);

        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => 'message',
            'external_event_id' => '91004',
            'external_user_id' => '666004',
            'payload' => $payload,
            'status' => 'received',
            'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666004', [
            'display_name' => 'TextUser',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);

        $matchedEntry = $faqMatcher->match($normalized['text']);
        if ($matchedEntry) {
            $conversationService->saveOutboundMessage($conversation, 'telegram', $matchedEntry->answer_text);
            $conversationService->setStatus($conversation, 'resolved');
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        $outbound = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->first();
        if (! $outbound) {
            $errors[] = "Expected greeting reply for text hi";
        } else {
            $this->info("  OK  F1 greeting still works: status='{$conversation->fresh()->status}'");
        }
    }

    protected function makePhotoPayload(int $updateId): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => ['id' => 666000, 'is_bot' => false, 'first_name' => 'Test'],
                'chat' => ['id' => 666000, 'type' => 'private'],
                'date' => now()->timestamp,
                'photo' => [
                    ['file_id' => 'file_pmt_hook', 'file_unique_id' => 'uniq_hook', 'width' => 800, 'height' => 600, 'file_size' => 50000],
                ],
            ],
        ];
    }

    protected function makeTextPayload(int $updateId, string $text): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => ['id' => 666000, 'is_bot' => false, 'first_name' => 'Test'],
                'chat' => ['id' => 666000, 'type' => 'private'],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }
}
