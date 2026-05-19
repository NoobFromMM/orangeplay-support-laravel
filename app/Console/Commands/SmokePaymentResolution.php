<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\PaymentCase;
use App\Services\Payments\PaymentCaseResolutionService;
use App\Services\Support\ConversationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokePaymentResolution extends Command
{
    protected $signature = 'smoke:payment-resolution';
    protected $description = 'Smoke test for F6 P6A payment case resolution service';

    public function handle(
        ConversationService $conversationService,
    ): int {
        $this->info('Payment Resolution Smoke Test');
        $this->info('=============================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test A: approve pending_review case');
            $this->testApprove($conversationService, $errors);

            $this->info('Test B: reject pending_review case');
            $this->testReject($conversationService, $errors);

            $this->info('Test C: approve needs_email case (blocked)');
            $this->testBlockNeedsEmail($conversationService, $errors);

            $this->info('Test D: duplicate approve (blocked)');
            $this->testDuplicateApprove($conversationService, $errors);

            $this->info('Test E: reject an approved case (blocked)');
            $this->testRejectApproved($conversationService, $errors);

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

    protected function testApprove(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '88801', ['display_name' => 'ApproveTest']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $case = PaymentCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'provider' => 'kbzpay',
            'transaction_id' => 'TEST-APPROVE',
            'status' => 'pending_review',
        ]);

        $service = new PaymentCaseResolutionService;
        $result = $service->approve($case, [
            'reviewer_id' => 1,
            'reviewer_name' => 'Admin Test',
            'note' => 'Looks correct',
        ]);

        if ($result->fresh()->status !== 'approved') {
            $errors[] = "Expected status='approved', got '{$result->fresh()->status}'";
        } else {
            $this->info("  OK  status='approved'");
        }

        if ($result->fresh()->reviewed_at === null) {
            $errors[] = "Expected reviewed_at to be set";
        } else {
            $this->info('  OK  reviewed_at set');
        }

        $updateMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_status_update')
            ->first();
        if (! $updateMsg) {
            $errors[] = "Expected payment_status_update message";
        } else {
            $this->info('  OK  payment_status_update message created');
        }

        $meta = $updateMsg->metadata ?? [];
        if (($meta['action'] ?? '') !== 'approved') {
            $errors[] = "Expected metadata.action='approved'";
        } else {
            $this->info("  OK  metadata.action='approved'");
        }
        if (($meta['payment_case_id'] ?? 0) !== $case->id) {
            $errors[] = "Expected metadata.payment_case_id={$case->id}";
        } else {
            $this->info('  OK  metadata.payment_case_id linked');
        }
        if (($meta['reviewer_id'] ?? null) !== 1) {
            $errors[] = "Expected metadata.reviewer_id=1";
        }
        if (($meta['reviewer_name'] ?? '') !== 'Admin Test') {
            $errors[] = "Expected metadata.reviewer_name='Admin Test'";
        }

        $this->newLine();
    }

    protected function testReject(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '88802', ['display_name' => 'RejectTest']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $case = PaymentCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'provider' => 'wavepay',
            'transaction_id' => 'TEST-REJECT',
            'status' => 'pending_review',
        ]);

        $service = new PaymentCaseResolutionService;
        $result = $service->reject($case, ['note' => 'Mismatch']);

        if ($result->fresh()->status !== 'rejected') {
            $errors[] = "Expected status='rejected'";
        } else {
            $this->info("  OK  status='rejected'");
        }

        $updateMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_status_update')
            ->first();
        $meta = $updateMsg->metadata ?? [];
        if (($meta['action'] ?? '') !== 'rejected') {
            $errors[] = "Expected metadata.action='rejected'";
        } else {
            $this->info("  OK  metadata.action='rejected'");
        }

        $this->newLine();
    }

    protected function testBlockNeedsEmail(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '88803', ['display_name' => 'NeedsEmail']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $case = PaymentCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'provider' => 'ayapay',
            'transaction_id' => 'TEST-NEEDS-EMAIL',
            'status' => 'needs_email',
        ]);

        $service = new PaymentCaseResolutionService;
        $thrown = false;
        try {
            $service->approve($case);
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }

        if (! $thrown) {
            $errors[] = "Expected InvalidArgumentException for needs_email case";
        } else {
            $this->info('  OK  InvalidArgumentException for needs_email case');
        }

        if ($case->fresh()->status !== 'needs_email') {
            $errors[] = "Expected status unchanged (needs_email)";
        } else {
            $this->info("  OK  status unchanged='needs_email'");
        }

        $updateCount = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_status_update')->count();
        if ($updateCount > 0) {
            $errors[] = "Expected no payment_status_update for blocked action";
        } else {
            $this->info('  OK  No payment_status_update created');
        }

        $this->newLine();
    }

    protected function testDuplicateApprove(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '88804', ['display_name' => 'DupApprove']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $case = PaymentCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'provider' => 'cbpay',
            'transaction_id' => 'TEST-DUP',
            'status' => 'approved',
        ]);

        $service = new PaymentCaseResolutionService;
        $thrown = false;
        try {
            $service->approve($case);
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }

        if (! $thrown) {
            $errors[] = "Expected InvalidArgumentException for already approved case";
        } else {
            $this->info('  OK  InvalidArgumentException for already approved case');
        }

        $updateCount = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_status_update')->count();
        if ($updateCount > 0) {
            $errors[] = "Expected no duplicate payment_status_update";
        } else {
            $this->info('  OK  No duplicate payment_status_update');
        }

        $this->newLine();
    }

    protected function testRejectApproved(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '88805', ['display_name' => 'RejApproved']);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $case = PaymentCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'provider' => 'uabpay',
            'transaction_id' => 'TEST-REJ-APPROVED',
            'status' => 'approved',
        ]);

        $service = new PaymentCaseResolutionService;
        $thrown = false;
        try {
            $service->reject($case);
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }

        if (! $thrown) {
            $errors[] = "Expected InvalidArgumentException for rejecting approved case";
        } else {
            $this->info('  OK  InvalidArgumentException for rejecting approved case');
        }

        if ($case->fresh()->status !== 'approved') {
            $errors[] = "Expected status remains 'approved'";
        } else {
            $this->info("  OK  status remains 'approved'");
        }

        $this->newLine();
    }
}
