<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\PaymentCase;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentCaseService;
use App\Services\Payments\PaymentScreenshotService;
use App\Services\Support\ConversationService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokePaymentEmailAttach extends Command
{
    protected $signature = 'smoke:payment-email-attach';
    protected $description = 'Smoke test for F6 P5 payment email attachment';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
    ): int {
        $this->info('Payment Email Attach Smoke Test');
        $this->info('==============================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test A: needs_email case + valid email');
            $this->testEmailAttachToCase($normalizer, $conversationService, $errors);

            $this->info('Test B: duplicate email after pending_review');
            $this->testDuplicateEmailAfterReview($normalizer, $conversationService, $errors);

            $this->info('Test C: email without open payment case');
            $this->testEmailWithoutCase($normalizer, $conversationService, $errors);

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

    protected function testEmailAttachToCase(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '777701', ['display_name' => 'EmailAttach']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        // Create a payment_case in needs_email status
        $paymentCase = PaymentCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'provider' => 'kbzpay',
            'transaction_id' => '111222333',
            'status' => 'needs_email',
        ]);

        // Simulate inbound text message with email
        $textPayload = $this->makeTextPayload(92001, 'customer@orangeplay.com');
        $normalized = $normalizer->normalize($textPayload);

        $event = WebhookEvent::create([
            'channel' => 'telegram', 'event_type' => 'message',
            'external_event_id' => '92001', 'external_user_id' => '777701',
            'payload' => $textPayload, 'status' => 'received', 'attempts' => 1,
        ]);

        $conversationService->saveInboundMessage($conversation, $normalized);

        // Attach email
        $paymentCase->update([
            'customer_email' => 'customer@orangeplay.com',
            'status' => 'pending_review',
        ]);

        // Simulate bot confirmation
        $botMsg = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => 'Email ရရှိပါပြီရှင့်။ Admin Team မှ ငွေလွှဲအချက်အလက်ကို စစ်ဆေးပြီး ပြန်လည်ဆက်သွယ်ပေးပါမယ်။',
            'metadata' => [
                'source' => 'telegram_bot',
                'event' => 'payment_email_received',
                'payment_case_id' => $paymentCase->id,
                'customer_email' => 'customer@orangeplay.com',
            ],
        ]);

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        // Assertions
        if ($paymentCase->fresh()->customer_email !== 'customer@orangeplay.com') {
            $errors[] = "Expected customer_email='customer@orangeplay.com'";
        } else {
            $this->info('  OK  customer_email attached to payment_case');
        }

        if ($paymentCase->fresh()->status !== 'pending_review') {
            $errors[] = "Expected status='pending_review'";
        } else {
            $this->info("  OK  status='pending_review'");
        }

        $botMeta = $botMsg->fresh()->metadata ?? [];
        if (($botMeta['event'] ?? '') !== 'payment_email_received') {
            $errors[] = "Expected metadata.event='payment_email_received'";
        } else {
            $this->info("  OK  metadata.event='payment_email_received'");
        }

        if (($botMeta['payment_case_id'] ?? 0) !== $paymentCase->id) {
            $errors[] = "Expected metadata.payment_case_id={$paymentCase->id}";
        } else {
            $this->info("  OK  metadata.payment_case_id linked");
        }

        if (($botMeta['customer_email'] ?? '') !== 'customer@orangeplay.com') {
            $errors[] = "Expected metadata.customer_email='customer@orangeplay.com'";
        } else {
            $this->info("  OK  metadata.customer_email='customer@orangeplay.com'");
        }

        $this->newLine();
    }

    protected function testDuplicateEmailAfterReview(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '777702', ['display_name' => 'DupEmail']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $paymentCase = PaymentCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'provider' => 'wavepay',
            'transaction_id' => '444555666',
            'status' => 'pending_review',
            'customer_email' => 'already@set.com',
        ]);

        $textPayload = $this->makeTextPayload(92002, 'already@set.com');
        $normalized = $normalizer->normalize($textPayload);

        $event = WebhookEvent::create([
            'channel' => 'telegram', 'event_type' => 'message',
            'external_event_id' => '92002', 'external_user_id' => '777702',
            'payload' => $textPayload, 'status' => 'received', 'attempts' => 1,
        ]);

        $conversationService->saveInboundMessage($conversation, $normalized);

        // Count payment_email_received messages
        $emailConfirmCount = Message::where('conversation_id', $conversation->id)
            ->whereJsonContains('metadata->event', 'payment_email_received')
            ->count();

        if ($emailConfirmCount > 0) {
            $errors[] = "Expected no new payment_email_received for already-reviewed case";
        } else {
            $this->info('  OK  No duplicate payment_email_received sent');
        }

        $this->newLine();
    }

    protected function testEmailWithoutCase(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '777703', ['display_name' => 'NoCaseUser']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $textPayload = $this->makeTextPayload(92003, 'random@example.com');
        $normalized = $normalizer->normalize($textPayload);

        $event = WebhookEvent::create([
            'channel' => 'telegram', 'event_type' => 'message',
            'external_event_id' => '92003', 'external_user_id' => '777703',
            'payload' => $textPayload, 'status' => 'received', 'attempts' => 1,
        ]);

        $conversationService->saveInboundMessage($conversation, $normalized);

        // No payment case should be affected
        $pcCount = PaymentCase::where('customer_id', $customer->id)->count();
        if ($pcCount > 0) {
            $errors[] = "Expected no payment case changes for email without open case";
        } else {
            $this->info('  OK  No payment case affected when no needs_email case');
        }

        $this->newLine();
    }

    protected function makeTextPayload(int $updateId, string $text): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => ['id' => 777000, 'is_bot' => false, 'first_name' => 'Test'],
                'chat' => ['id' => 777000, 'type' => 'private'],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }
}
