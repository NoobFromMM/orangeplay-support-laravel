<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerEmail;
use App\Services\Customers\CustomerEmailCaptureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokeCustomerEmails extends Command
{
    protected $signature = 'smoke:customer-emails';
    protected $description = 'Smoke test for P10B customer emails foundation';

    public function handle(): int
    {
        $this->info('Customer Emails Smoke Test');
        $this->info('==========================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test A: capture first email');
            $this->testFirstCapture($errors);

            $this->info('Test B: capture same email different casing');
            $this->testSameEmailDiffCasing($errors);

            $this->info('Test C: capture second email same customer');
            $this->testSecondEmail($errors);

            $this->info('Test D: invalid email rejected');
            $this->testInvalidEmail($errors);

            $this->info('Test E: different customer same email');
            $this->testDiffCustomerSameEmail($errors);

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

    protected function testFirstCapture(array &$errors): void
    {
        $customer = Customer::create(['platform' => 'telegram', 'platform_user_id' => 'test_email_1']);

        $svc = new CustomerEmailCaptureService;
        $ce = $svc->capture($customer, 'User@Example.com', 'telegram_text', ['test' => true]);

        if (! $ce->exists) { $errors[] = "Email not created"; return; }
        $this->info('  OK  Row created');

        if ($ce->normalized_email !== 'user@example.com') {
            $errors[] = "Expected normalized='user@example.com', got '{$ce->normalized_email}'";
        } else {
            $this->info("  OK  normalized='user@example.com'");
        }

        if ($ce->source !== 'telegram_text') {
            $errors[] = "Expected source='telegram_text'";
        } else {
            $this->info("  OK  source='telegram_text'");
        }

        if ($ce->first_seen_at === null || $ce->last_seen_at === null) {
            $errors[] = "Expected first_seen_at and last_seen_at";
        } else {
            $this->info('  OK  first_seen_at and last_seen_at set');
        }

        $this->newLine();
    }

    protected function testSameEmailDiffCasing(array &$errors): void
    {
        $customer = Customer::create(['platform' => 'telegram', 'platform_user_id' => 'test_email_2']);

        $svc = new CustomerEmailCaptureService;
        $first = $svc->capture($customer, 'test@example.com');
        $second = $svc->capture($customer, 'TEST@EXAMPLE.COM');

        if ($first->id !== $second->id) {
            $errors[] = "Expected same row for same email diff casing";
        } else {
            $this->info('  OK  Same row returned (no duplicate)');
        }

        if (CustomerEmail::where('customer_id', $customer->id)->count() !== 1) {
            $errors[] = "Expected exactly 1 row";
        } else {
            $this->info('  OK  Exactly 1 row (no duplicate)');
        }

        $this->newLine();
    }

    protected function testSecondEmail(array &$errors): void
    {
        $customer = Customer::create(['platform' => 'telegram', 'platform_user_id' => 'test_email_3']);

        $svc = new CustomerEmailCaptureService;
        $svc->capture($customer, 'first@example.com');
        $svc->capture($customer, 'second@example.com');

        if (CustomerEmail::where('customer_id', $customer->id)->count() !== 2) {
            $errors[] = "Expected 2 rows for 2 different emails";
        } else {
            $this->info('  OK  2 different emails = 2 rows');
        }

        $this->newLine();
    }

    protected function testInvalidEmail(array &$errors): void
    {
        $customer = Customer::create(['platform' => 'telegram', 'platform_user_id' => 'test_email_4']);

        $svc = new CustomerEmailCaptureService;
        $thrown = false;
        try {
            $svc->capture($customer, 'not-an-email');
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }

        if (! $thrown) {
            $errors[] = "Expected InvalidArgumentException for invalid email";
        } else {
            $this->info('  OK  InvalidArgumentException for invalid email');
        }

        if (CustomerEmail::where('customer_id', $customer->id)->count() > 0) {
            $errors[] = "Expected no row for invalid email";
        } else {
            $this->info('  OK  No row created for invalid email');
        }

        $this->newLine();
    }

    protected function testDiffCustomerSameEmail(array &$errors): void
    {
        $c1 = Customer::create(['platform' => 'telegram', 'platform_user_id' => 'test_email_5a']);
        $c2 = Customer::create(['platform' => 'telegram', 'platform_user_id' => 'test_email_5b']);

        $svc = new CustomerEmailCaptureService;
        $svc->capture($c1, 'shared@example.com');
        $svc->capture($c2, 'shared@example.com');

        if (CustomerEmail::where('customer_id', $c1->id)->count() !== 1) {
            $errors[] = "C1: expected 1 row";
        }
        if (CustomerEmail::where('customer_id', $c2->id)->count() !== 1) {
            $errors[] = "C2: expected 1 row";
        }
        $this->info('  OK  Different customers same email = separate rows');

        $this->newLine();
    }
}
