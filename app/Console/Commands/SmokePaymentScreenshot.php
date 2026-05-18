<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\Payments\PaymentCaseService;
use App\Services\Payments\PaymentScreenshotService;
use App\Services\Support\ConversationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokePaymentScreenshot extends Command
{
    protected $signature = 'smoke:payment-screenshot';
    protected $description = 'Smoke test for F6 P2 payment screenshot processing service';

    public function handle(
        ConversationService $conversationService,
    ): int {
        $this->info('Payment Screenshot Processing Smoke Test');
        $this->info('========================================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test A: is_payment=true with transaction_id');
            $this->testPaymentDetected($conversationService, $errors);

            $this->info('Test B: is_payment=true with transaction_id=null');
            $this->testPaymentDetectedNoTxn($conversationService, $errors);

            $this->info('Test C: is_payment=false — returns null, nothing created');
            $this->testNotPayment($conversationService, $errors);

            $this->info('Test D: Non-image message throws exception');
            $this->testNonImageMessage($conversationService, $errors);

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

    protected function testPaymentDetected(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '999001', [
            'display_name' => 'PmtUser',
            'username' => 'pmtuser',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $imageMsg = $conversationService->saveInboundMessage($conversation, [
            'platform' => 'telegram',
            'platform_user_id' => '999001',
            'display_name' => 'PmtUser',
            'message_type' => 'image',
            'text' => null,
            'raw_payload' => null,
            'metadata' => ['telegram_file_id' => 'file_pmt', 'width' => 800, 'height' => 600],
        ]);

        $workerResult = [
            'ok' => true,
            'is_payment' => true,
            'provider' => 'KBZ Pay',
            'transaction_id' => '1429501235',
            'amount' => 5000,
            'confidence' => 0.95,
        ];

        $service = new PaymentScreenshotService(app(PaymentCaseService::class));
        $case = $service->processImageMessage($imageMsg, $workerResult);

        if (! $case) {
            $errors[] = "Expected payment case to be created";
            return;
        }
        $this->info('  OK  Payment case created');

        if ($case->provider !== 'KBZ Pay') {
            $errors[] = "Expected provider='KBZ Pay'";
        } else {
            $this->info("  OK  provider='KBZ Pay'");
        }

        if ($case->transaction_id !== '1429501235') {
            $errors[] = "Expected transaction_id='1429501235'";
        } else {
            $this->info("  OK  transaction_id='1429501235'");
        }

        if ($case->image_message_id !== $imageMsg->id) {
            $errors[] = "Expected image_message_id={$imageMsg->id}";
        } else {
            $this->info("  OK  image_message_id linked");
        }

        // Assert review card message
        $review = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_review_card')
            ->first();

        if (! $review) {
            $errors[] = "Expected payment_review_card message in timeline";
        } else {
            $this->info('  OK  payment_review_card message created');
        }

        $meta = $review->metadata ?? [];
        if (($meta['payment_case_id'] ?? 0) !== $case->id) {
            $errors[] = "Expected metadata.payment_case_id={$case->id}";
        } else {
            $this->info('  OK  metadata links payment_case_id');
        }

        if (($meta['transaction_id'] ?? '') !== '1429501235') {
            $errors[] = "Expected metadata.transaction_id='1429501235'";
        } else {
            $this->info("  OK  metadata.transaction_id='1429501235'");
        }
    }

    protected function testPaymentDetectedNoTxn(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '999002', [
            'display_name' => 'NoTxnPmt',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $imageMsg = $conversationService->saveInboundMessage($conversation, [
            'platform' => 'telegram',
            'platform_user_id' => '999002',
            'display_name' => 'NoTxnPmt',
            'message_type' => 'image',
            'text' => null,
            'raw_payload' => null,
            'metadata' => ['telegram_file_id' => 'file_notxn'],
        ]);

        $workerResult = [
            'ok' => true,
            'is_payment' => true,
            'provider' => 'Wave Pay',
            'transaction_id' => null,
            'amount' => 3000,
        ];

        $service = new PaymentScreenshotService(app(PaymentCaseService::class));
        $case = $service->processImageMessage($imageMsg, $workerResult);

        if (! $case) {
            $errors[] = "Expected payment case with null transaction_id";
            return;
        }
        $this->info('  OK  Payment case created (null transaction_id)');

        if ($case->transaction_id !== null) {
            $errors[] = "Expected transaction_id=null";
        } else {
            $this->info('  OK  transaction_id=null accepted');
        }

        $review = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_review_card')
            ->first();

        if (! $review) {
            $errors[] = "Expected review card with null transaction_id";
        } else {
            $this->info('  OK  Review card created with null transaction_id');
        }
    }

    protected function testNotPayment(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '999003', [
            'display_name' => 'NotPmt',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $imageMsg = $conversationService->saveInboundMessage($conversation, [
            'platform' => 'telegram',
            'platform_user_id' => '999003',
            'display_name' => 'NotPmt',
            'message_type' => 'image',
            'text' => null,
            'raw_payload' => null,
            'metadata' => ['telegram_file_id' => 'file_notpmt'],
        ]);

        $workerResult = [
            'ok' => true,
            'is_payment' => false,
        ];

        $service = new PaymentScreenshotService(app(PaymentCaseService::class));
        $case = $service->processImageMessage($imageMsg, $workerResult);

        if ($case !== null) {
            $errors[] = "Expected null for is_payment=false";
            return;
        }
        $this->info('  OK  returns null for is_payment=false');

        // No payment_case created
        $pcCount = \App\Models\PaymentCase::where('customer_id', $customer->id)->count();
        if ($pcCount > 0) {
            $errors[] = "Expected no payment cases, got {$pcCount}";
        } else {
            $this->info('  OK  No payment case created');
        }

        // No review card
        $reviewCount = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'payment_review_card')
            ->count();
        if ($reviewCount > 0) {
            $errors[] = "Expected no review cards, got {$reviewCount}";
        } else {
            $this->info('  OK  No review card created');
        }
    }

    protected function testNonImageMessage(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '999004', [
            'display_name' => 'TextUser',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $textMsg = $conversationService->saveInboundMessage($conversation, [
            'platform' => 'telegram',
            'platform_user_id' => '999004',
            'display_name' => 'TextUser',
            'message_type' => 'text',
            'text' => 'hello',
            'raw_payload' => null,
            'metadata' => null,
        ]);

        $workerResult = [
            'ok' => true,
            'is_payment' => true,
            'provider' => 'Test',
        ];

        $service = new PaymentScreenshotService(app(PaymentCaseService::class));

        $thrown = false;
        try {
            $service->processImageMessage($textMsg, $workerResult);
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }

        if (! $thrown) {
            $errors[] = "Expected InvalidArgumentException for non-image message";
        } else {
            $this->info('  OK  InvalidArgumentException for non-image message');
        }
    }
}
