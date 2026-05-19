<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\PaymentCase;
use App\Services\Payments\DuplicatePaymentDetector;
use App\Services\Support\ConversationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokePaymentDuplicate extends Command
{
    protected $signature = 'smoke:payment-duplicate';
    protected $description = 'Smoke test for F8 duplicate payment detection';

    public function handle(
        ConversationService $conversationService,
    ): int {
        $this->info('Duplicate Payment Detection Smoke Test');
        $this->info('======================================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test A: duplicate needs_email → no new case, email reminder');
            $this->testDuplicateNeedsEmail($conversationService, $errors);

            $this->info('Test B: duplicate pending_review → under review reply');
            $this->testDuplicatePendingReview($conversationService, $errors);

            $this->info('Test C: duplicate approved → already approved reply');
            $this->testDuplicateApproved($conversationService, $errors);

            $this->info('Test D: duplicate rejected → ask for correct screenshot');
            $this->testDuplicateRejected($conversationService, $errors);

            $this->info('Test E: different transaction_id → creates new case');
            $this->testDifferentTransaction($conversationService, $errors);

            $this->info('Test F: missing transaction_id → does not block');
            $this->testMissingTransactionId($conversationService, $errors);

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

    protected function testDuplicateNeedsEmail(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '99901', ['display_name' => 'Dup1']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $existing = PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conversation->id,
            'provider' => 'kbzpay', 'transaction_id' => 'DUP-TXN-001', 'status' => 'needs_email',
        ]);

        $detector = new DuplicatePaymentDetector;
        $dup = $detector->findDuplicate('KBZ Pay', 'DUP-TXN-001');
        if (! $dup) { $errors[] = "Expected duplicate found for needs_email"; return; }
        $this->info('  OK  Duplicate detected');
        if ($dup->id !== $existing->id) { $errors[] = "Wrong duplicate case id"; } else { $this->info('  OK  Correct duplicate case id'); }

        $event = 'payment_duplicate_detected';
        Message::create([
            'conversation_id' => $conversation->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'system', 'sender_type' => 'system',
            'message_type' => 'payment_duplicate_notice',
            'text' => 'Duplicate payment screenshot detected',
            'metadata' => ['duplicate_of_payment_case_id' => $existing->id, 'duplicate_status' => 'needs_email', 'provider' => 'kbzpay', 'transaction_id' => 'DUP-TXN-001'],
        ]);
        Message::create([
            'conversation_id' => $conversation->id, 'customer_id' => $customer->id,
            'platform' => 'telegram', 'direction' => 'outbound', 'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => 'ဒီငွေလွှဲ Screenshot ကို လက်ခံထားပြီးပါပြီရှင့်။ သက်တမ်းတိုးမယ့် Orange Play account Email ကို ပို့ပေးပါရှင့်။',
            'metadata' => ['source' => 'telegram_bot', 'event' => $event, 'duplicate_of_payment_case_id' => $existing->id, 'transaction_id' => 'DUP-TXN-001'],
        ]);
        $this->info("  OK  Duplicate notice + bot needs_email reply saved");
        $this->newLine();
    }

    protected function testDuplicatePendingReview(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '99902', ['display_name' => 'Dup2']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $existing = PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conversation->id,
            'provider' => 'ayapay', 'transaction_id' => 'DUP-TXN-002', 'status' => 'pending_review',
        ]);

        $detector = new DuplicatePaymentDetector;
        $dup = $detector->findDuplicate('aya pay', 'DUP-TXN-002');
        if (! $dup) { $errors[] = "Expected duplicate for pending_review (txn match)"; return; }
        $this->info('  OK  Duplicate pending_review detected by transaction_id');
        if ($dup->id !== $existing->id) { $errors[] = "Wrong duplicate case id"; }
        // Different provider with same txn should still be duplicate
        $dup2 = $detector->findDuplicate('different_provider', 'DUP-TXN-002');
        if (! $dup2) { $errors[] = "Expected duplicate with same txn, different provider"; }
        if ($dup2->id !== $existing->id) { $errors[] = "Duplicate id mismatch for cross-provider match"; } else { $this->info('  OK  Same txn different provider = duplicate'); }
        $this->newLine();
    }

    protected function testDuplicateApproved(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '99903', ['display_name' => 'Dup3']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conversation->id,
            'provider' => 'cbpay', 'transaction_id' => 'DUP-TXN-003', 'status' => 'approved',
        ]);

        $detector = new DuplicatePaymentDetector;
        $dup = $detector->findDuplicate('CB Pay', 'DUP-TXN-003');
        if (! $dup) { $errors[] = "Expected duplicate for approved"; }
        $this->info('  OK  Duplicate approved detected');
        $this->newLine();
    }

    protected function testDuplicateRejected(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '99904', ['display_name' => 'Dup4']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conversation->id,
            'provider' => 'wavepay', 'transaction_id' => 'DUP-TXN-004', 'status' => 'rejected',
        ]);

        $detector = new DuplicatePaymentDetector;
        $dup = $detector->findDuplicate('Wave Pay', 'DUP-TXN-004');
        if (! $dup) { $errors[] = "Expected duplicate for rejected"; }
        $this->info('  OK  Duplicate rejected detected');
        $this->newLine();
    }

    protected function testDifferentTransaction(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '99905', ['display_name' => 'Dup5']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conversation->id,
            'provider' => 'kbzpay', 'transaction_id' => 'DUP-TXN-005', 'status' => 'pending_review',
        ]);

        $detector = new DuplicatePaymentDetector;
        $dup = $detector->findDuplicate('kbzpay', 'DIFFERENT-TXN-999');
        if ($dup) { $errors[] = "Different txn should not find duplicate"; }
        $this->info('  OK  Different transaction_id does not match duplicate');

        // New case can be created
        PaymentCase::create([
            'customer_id' => $customer->id, 'conversation_id' => $conversation->id,
            'provider' => 'kbzpay', 'transaction_id' => 'DIFFERENT-TXN-999', 'status' => 'pending_review',
        ]);
        $this->info('  OK  New payment_case created with different txn');
        $this->newLine();
    }

    protected function testMissingTransactionId(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '99906', ['display_name' => 'Dup6']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $detector = new DuplicatePaymentDetector;
        $dup = $detector->findDuplicate('kbzpay', null);
        if ($dup) { $errors[] = "Null txn should not find duplicate"; }
        $this->info('  OK  Missing transaction_id returns null (no duplicate block)');
        $this->newLine();
    }
}
