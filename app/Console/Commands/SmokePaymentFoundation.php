<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\PaymentCase;
use App\Services\Payments\PaymentCaseService;
use App\Services\Payments\PaymentCheckClient;
use App\Services\Support\ConversationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SmokePaymentFoundation extends Command
{
    protected $signature = 'smoke:payment-foundation';
    protected $description = 'Smoke test for F6 P1 payment case foundation';

    public function handle(
        ConversationService $conversationService,
    ): int {
        $this->info('Payment Foundation Smoke Test');
        $this->info('=============================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test A: Worker URL missing — safe failure');
            $this->testWorkerUrlMissing($errors);

            $this->info('Test B: Worker fake success — normalized result');
            $this->testWorkerFakeSuccess($errors);

            $this->info('Test C: PaymentCaseService creates case');
            $this->testCreatePaymentCase($conversationService, $errors);

            $this->info('Test D: Missing transaction_id still creates case');
            $this->testMissingTransactionId($conversationService, $errors);

            $this->info('Test E: is_payment=false throws exception');
            $this->testIsPaymentFalse($conversationService, $errors);

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

    protected function testWorkerUrlMissing(array &$errors): void
    {
        $client = app(PaymentCheckClient::class);
        $result = $client->checkImageBytes('fake-bytes');

        if (($result['ok'] ?? true) !== false) {
            $errors[] = "Expected ok=false when URL missing, got " . json_encode($result);
        }
        if (($result['is_payment'] ?? true) !== false) {
            $errors[] = "Expected is_payment=false when URL missing";
        }
        $this->info('  OK  Returns safe failure when PAYMENT_CHECK_WORKER_URL not set');
    }

    protected function testWorkerFakeSuccess(array &$errors): void
    {
        $fakeResponse = [
            'ok' => true,
            'is_payment' => true,
            'provider' => 'KBZ Pay',
            'transaction_id' => '1429501235',
            'amount' => 5000,
            'confidence' => 0.95,
            'reason' => 'test',
        ];

        Http::fake([
            'payment-check.example.com/*' => Http::response($fakeResponse, 200),
        ]);

        config(['app.env' => 'testing']);
        $_ENV['PAYMENT_CHECK_WORKER_URL'] = 'https://payment-check.example.com/check';

        $client = app(PaymentCheckClient::class);
        $result = $client->checkImageBytes('fake-bytes', ['platform' => 'telegram']);

        if (($result['ok'] ?? false) !== true) {
            $errors[] = "Expected ok=true, got " . json_encode($result);
        }
        if (($result['is_payment'] ?? false) !== true) {
            $errors[] = "Expected is_payment=true";
        }
        if (($result['provider'] ?? '') !== 'KBZ Pay') {
            $errors[] = "Expected provider='KBZ Pay', got '{$result['provider']}'";
        }
        if (($result['transaction_id'] ?? '') !== '1429501235') {
            $errors[] = "Expected transaction_id='1429501235', got '{$result['transaction_id']}'";
        }
        if (($result['amount'] ?? 0) !== 5000) {
            $errors[] = "Expected amount=5000";
        }

        $this->info('  OK  Normalized result with provider, transaction_id, amount');

        // Clean up env for other tests
        unset($_ENV['PAYMENT_CHECK_WORKER_URL']);
    }

    protected function testCreatePaymentCase(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '888001', [
            'display_name' => 'Payment User',
            'username' => 'paymentuser',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);

        // Save an image message
        $imageMsg = $conversationService->saveInboundMessage($conversation, [
            'platform' => 'telegram',
            'platform_user_id' => '888001',
            'display_name' => 'Payment User',
            'username' => 'paymentuser',
            'message_type' => 'image',
            'text' => null,
            'raw_payload' => null,
            'metadata' => ['telegram_file_id' => 'file_xyz', 'width' => 800, 'height' => 600],
        ]);

        $workerResult = [
            'ok' => true,
            'is_payment' => true,
            'provider' => 'KBZ Pay',
            'transaction_id' => '1429501235',
            'amount' => 5000,
            'confidence' => 0.95,
        ];

        $service = app(PaymentCaseService::class);
        $case = $service->createFromWorkerResult($customer, $conversation, $imageMsg, $workerResult);

        if (! $case->exists) {
            $errors[] = "Payment case not created";
            return;
        }
        $this->info('  OK  Payment case created');

        if ($case->provider !== 'KBZ Pay') {
            $errors[] = "Expected provider='KBZ Pay', got '{$case->provider}'";
        } else {
            $this->info("  OK  provider='KBZ Pay'");
        }

        if ($case->transaction_id !== '1429501235') {
            $errors[] = "Expected transaction_id='1429501235', got '{$case->transaction_id}'";
        } else {
            $this->info("  OK  transaction_id='1429501235'");
        }

        if ($case->status !== 'pending_review') {
            $errors[] = "Expected status='pending_review', got '{$case->status}'";
        } else {
            $this->info("  OK  status='pending_review'");
        }

        $saved = $case->worker_response;
        if (($saved['provider'] ?? '') !== 'KBZ Pay') {
            $errors[] = "Expected worker_response saved with provider";
        } else {
            $this->info('  OK  worker_response saved');
        }

        if ($case->image_message_id !== $imageMsg->id) {
            $errors[] = "Expected image_message_id={$imageMsg->id}, got {$case->image_message_id}";
        } else {
            $this->info("  OK  image_message_id={$imageMsg->id}");
        }
    }

    protected function testMissingTransactionId(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '888002', [
            'display_name' => 'NoTxn User',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $workerResult = [
            'ok' => true,
            'is_payment' => true,
            'provider' => 'Wave Pay',
            'transaction_id' => null,
            'amount' => 3000,
        ];

        $service = app(PaymentCaseService::class);
        $case = $service->createFromWorkerResult($customer, $conversation, null, $workerResult);

        if ($case->transaction_id !== null) {
            $errors[] = "Expected transaction_id=null, got '{$case->transaction_id}'";
        } else {
            $this->info('  OK  transaction_id=null accepted');
        }

        if ($case->status !== 'pending_review') {
            $errors[] = "Expected status='pending_review', got '{$case->status}'";
        } else {
            $this->info("  OK  status='pending_review' with missing transaction_id");
        }
    }

    protected function testIsPaymentFalse(
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $customer = $conversationService->findOrCreateCustomer('telegram', '888003', [
            'display_name' => 'NotPayment',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $workerResult = [
            'ok' => true,
            'is_payment' => false,
        ];

        $service = app(PaymentCaseService::class);

        $thrown = false;
        try {
            $service->createFromWorkerResult($customer, $conversation, null, $workerResult);
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }

        if (! $thrown) {
            $errors[] = "Expected InvalidArgumentException for is_payment=false";
        } else {
            $this->info('  OK  InvalidArgumentException thrown for is_payment=false');
        }
    }
}
