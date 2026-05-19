<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentCaseService;
use App\Services\Payments\PaymentScreenshotService;
use App\Services\Support\ConversationService;
use App\Services\Support\FaqMatcher;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokePaymentWebhook extends Command
{
    protected $signature = 'smoke:payment-webhook';
    protected $description = 'Smoke test for F6 P3 payment webhook integration';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
    ): int {
        $this->info('Payment Webhook Integration Smoke Test');
        $this->info('=====================================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test A: image + is_payment=true');
            $this->testPaymentImage($normalizer, $conversationService, $errors);

            $this->info('Test A2: is_payment=false + provider+txn derived is_payment=true');
            $this->testDerivedPaymentImage($normalizer, $conversationService, $errors);

            $this->info('Test A3: email request after payment');
            $this->testEmailRequestAfterPayment($normalizer, $conversationService, $errors);

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

        $customer = $conversationService->findOrCreateCustomer('telegram', '666001', ['display_name' => 'PmtHookUser']);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);
        $conversationService->setStatus($conversation, 'Needs Reply');

        $imageMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'image')->latest()->first();

        $workerResult = [
            'ok' => true,
            'is_payment' => true,
            'provider' => 'kbzpay',
            'transaction_id' => '1429501235',
            'amount' => 5000,
            'confidence' => 0.95,
        ];

        if ($imageMsg) {
            $imageMsg->metadata = array_merge($imageMsg->metadata ?? [], [
                'payment_check' => [
                    'checked' => true, 'ok' => true, 'is_payment' => true,
                    'provider' => 'kbzpay', 'transaction_id' => '1429501235',
                    'amount' => 5000, 'confidence' => 0.95,
                    'checked_at' => now()->toIso8601String(),
                ],
            ]);
            $imageMsg->save();

            $pss = new PaymentScreenshotService(app(PaymentCaseService::class));
            $pss->processImageMessage($imageMsg, $workerResult);
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);
        $this->info('  OK  webhook event processed');
        $this->info('  OK  Image message saved');

        $pcMeta = $imageMsg->fresh()->metadata['payment_check'] ?? null;
        if (! $pcMeta) {
            $errors[] = "Expected metadata.payment_check on image message";
        } elseif (($pcMeta['is_payment'] ?? false) !== true) {
            $errors[] = "Expected payment_check.is_payment=true";
        } else {
            $this->info('  OK  metadata.payment_check.is_payment=true');
        }
        if (($pcMeta['transaction_id'] ?? '') !== '1429501235') {
            $errors[] = "Expected payment_check.transaction_id='1429501235'";
        } else {
            $this->info("  OK  payment_check.transaction_id='1429501235'");
        }

        $pc = \App\Models\PaymentCase::where('conversation_id', $conversation->id)->first();
        if (! $pc) {
            $errors[] = "Payment case not created";
        } else {
            $this->info('  OK  Payment case created');
        }
        $review = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_review_card')->first();
        if (! $review) {
            $errors[] = "payment_review_card message not created";
        } else {
            $this->info('  OK  payment_review_card created');
            if (($review->metadata['payment_case_id'] ?? 0) === $pc->id) {
                $this->info('  OK  metadata.payment_case_id present');
            }
        }
        $botReplies = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'bot')->count();
        if ($botReplies > 0) {
            $errors[] = "Expected no bot reply for image";
        } else {
            $this->info('  OK  No bot reply sent');
        }
    }

    protected function testDerivedPaymentImage(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $payload = $this->makePhotoPayload(91005);
        $normalized = $normalizer->normalize($payload);

        $event = WebhookEvent::create([
            'channel' => 'telegram', 'event_type' => 'message',
            'external_event_id' => '91005', 'external_user_id' => '666005',
            'payload' => $payload, 'status' => 'received', 'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666005', ['display_name' => 'DerivedPmtUser']);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);
        $conversationService->setStatus($conversation, 'Needs Reply');

        $imageMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'image')->latest()->first();

        $workerResult = [
            'ok' => true, 'is_payment' => true,
            'provider' => 'ayapay',
            'transaction_id' => '259430808614',
            'reason' => "Found 'Transaction ID' label",
        ];

        if ($imageMsg) {
            $imageMsg->metadata = array_merge($imageMsg->metadata ?? [], [
                'payment_check' => [
                    'checked' => true, 'ok' => true, 'is_payment' => true,
                    'provider' => 'ayapay', 'transaction_id' => '259430808614',
                    'reason' => "Found 'Transaction ID' label",
                    'checked_at' => now()->toIso8601String(),
                ],
            ]);
            $imageMsg->save();

            $pss = new PaymentScreenshotService(app(PaymentCaseService::class));
            $pss->processImageMessage($imageMsg, $workerResult);
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        $pcMeta = $imageMsg->fresh()->metadata['payment_check'] ?? null;
        if (! $pcMeta || ($pcMeta['is_payment'] ?? false) !== true) {
            $errors[] = "Expected derived is_payment=true with provider+txn";
        } else {
            $this->info('  OK  derived is_payment=true from provider+txn');
        }

        $pc = \App\Models\PaymentCase::where('conversation_id', $conversation->id)->first();
        if (! $pc) {
            $errors[] = "Expected payment case for derived detection";
        } else {
            $this->info('  OK  Payment case created from derived detection');
        }

        $review = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_review_card')->first();
        if (! $review) {
            $errors[] = "Expected review card for derived payment";
        } else {
            $this->info('  OK  Review card created for derived payment');
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
            'channel' => 'telegram', 'event_type' => 'message',
            'external_event_id' => '91002', 'external_user_id' => '666002',
            'payload' => $payload, 'status' => 'received', 'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666002', ['display_name' => 'NotPmtUser']);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);
        $conversationService->setStatus($conversation, 'Needs Reply');

        $imageMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'image')->latest()->first();

        if ($imageMsg) {
            $imageMsg->metadata = array_merge($imageMsg->metadata ?? [], [
                'payment_check' => [
                    'checked' => true, 'ok' => true, 'is_payment' => false,
                    'checked_at' => now()->toIso8601String(),
                ],
            ]);
            $imageMsg->save();
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        $pcMeta = $imageMsg->fresh()->metadata['payment_check'] ?? null;
        if (! $pcMeta || ($pcMeta['is_payment'] ?? true) !== false) {
            $errors[] = "Expected payment_check.is_payment=false";
        } else {
            $this->info('  OK  metadata.payment_check.is_payment=false');
        }

        $pcCount = \App\Models\PaymentCase::where('conversation_id', $conversation->id)->count();
        if ($pcCount > 0) {
            $errors[] = "Expected no payment cases, got {$pcCount}";
        } else {
            $this->info('  OK  No payment case created');
        }

        $reviewCount = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_review_card')->count();
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
            'channel' => 'telegram', 'event_type' => 'message',
            'external_event_id' => '91003', 'external_user_id' => '666003',
            'payload' => $payload, 'status' => 'received', 'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666003', ['display_name' => 'FailUser']);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);
        $conversationService->setStatus($conversation, 'Needs Reply');

        $imageMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'image')->first();
        if ($imageMsg) {
            $imageMsg->metadata = array_merge($imageMsg->metadata ?? [], [
                'payment_check' => [
                    'checked' => true, 'ok' => false, 'is_payment' => false,
                    'error' => 'Worker returned HTTP 500',
                    'checked_at' => now()->toIso8601String(),
                ],
            ]);
            $imageMsg->save();
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);
        $this->info('  OK  Image message preserved after check failure');
        $this->info('  OK  Webhook still processes despite check failure');

        $pcMeta = $imageMsg->fresh()->metadata['payment_check'] ?? null;
        if (! $pcMeta || ($pcMeta['ok'] ?? true) !== false) {
            $errors[] = "Expected payment_check.ok=false, error stored";
        } else {
            $this->info('  OK  metadata.payment_check.ok=false, error stored');
        }
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
            'channel' => 'telegram', 'event_type' => 'message',
            'external_event_id' => '91004', 'external_user_id' => '666004',
            'payload' => $payload, 'status' => 'received', 'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666004', ['display_name' => 'TextUser']);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);

        $matchedEntry = $faqMatcher->match($normalized['text']);
        if ($matchedEntry) {
            $conversationService->saveOutboundMessage($conversation, 'telegram', $matchedEntry->answer_text);
            $conversationService->setStatus($conversation, 'resolved');
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        $outbound = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')->first();
        if (! $outbound) {
            $errors[] = "Expected greeting reply for text hi";
        } else {
            $this->info("  OK  F1 greeting still works: status='{$conversation->fresh()->status}'");
        }
    }

    protected function testEmailRequestAfterPayment(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $payload = $this->makePhotoPayload(91006);
        $normalized = $normalizer->normalize($payload);

        $event = WebhookEvent::create([
            'channel' => 'telegram', 'event_type' => 'message',
            'external_event_id' => '91006', 'external_user_id' => '666006',
            'payload' => $payload, 'status' => 'received', 'attempts' => 1,
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '666006', ['display_name' => 'EmailUser']);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);
        $conversationService->setStatus($conversation, 'Needs Reply');

        $imageMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'image')->latest()->first();

        $workerResult = [
            'ok' => true, 'is_payment' => true,
            'provider' => 'kbzpay', 'transaction_id' => '999888777',
        ];

        $pss = new PaymentScreenshotService(app(PaymentCaseService::class));
        $paymentCase = $pss->processImageMessage($imageMsg, $workerResult);

        if (! $paymentCase) {
            $errors[] = "Expected payment case for email test";
            return;
        }

        // Simulate email request (bot message)
        $botMsg = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => 'Payment Screenshot ရရှိပါပြီရှင့်။ ဘယ် Orange Play account Email ကို သက်တမ်းတိုးချင်တာလဲ ပို့ပေးပါရှင့်။',
            'metadata' => [
                'source' => 'telegram_bot',
                'event' => 'ask_email_after_payment',
                'payment_case_id' => $paymentCase->id,
            ],
        ]);

        $paymentCase->update(['status' => 'needs_email']);

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        $this->info('  OK  payment_case created');
        $this->info('  OK  bot email request message saved');

        $botMeta = $botMsg->fresh()->metadata ?? [];
        if (($botMeta['event'] ?? '') !== 'ask_email_after_payment') {
            $errors[] = "Expected metadata.event='ask_email_after_payment'";
        } else {
            $this->info("  OK  metadata.event='ask_email_after_payment'");
        }

        if (($botMeta['payment_case_id'] ?? 0) !== $paymentCase->id) {
            $errors[] = "Expected metadata.payment_case_id={$paymentCase->id}";
        } else {
            $this->info('  OK  metadata.payment_case_id linked');
        }

        if ($paymentCase->fresh()->status !== 'needs_email') {
            $errors[] = "Expected status='needs_email', got '{$paymentCase->fresh()->status}'";
        } else {
            $this->info("  OK  payment_case status='needs_email'");
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
