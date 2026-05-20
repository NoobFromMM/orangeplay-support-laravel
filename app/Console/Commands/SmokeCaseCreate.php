<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SupportCase;
use App\Services\Support\ConversationService;
use App\Services\Support\SupportCaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokeCaseCreate extends Command
{
    protected $signature = 'smoke:case-create';

    protected $description = 'Smoke test for creating a support case from a customer message';

    public function handle(
        ConversationService $conversationService,
        SupportCaseService $supportCaseService,
    ): int {
        $this->info('Case Create Smoke Test');
        $this->info('======================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $customer = $conversationService->findOrCreateCustomer('telegram', 'case-smoke-user', [
                'display_name' => 'Case Smoke User',
                'username' => 'casesmoke',
            ]);

            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'status' => 'Needs Reply',
                'bot_paused' => true,
                'last_message_at' => now(),
            ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'platform' => 'telegram',
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'text' => 'ဒီကားတင်ပေးပါ',
                'raw_payload' => ['message_id' => 9991],
                'metadata' => null,
            ]);

            $case = $supportCaseService->createFromMessage($message, [
                'category' => 'movie_request',
                'title' => 'Movie request smoke',
                'description' => 'Smoke test case',
                'priority' => 'normal',
                'status' => 'open',
                'admin_note' => 'Smoke note',
            ]);

            $this->assertCase($case, $message, $errors);
        } catch (\Throwable $e) {
            $errors[] = 'Exception: ' . $e->getMessage();
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

    protected function assertCase(SupportCase $case, Message $message, array &$errors): void
    {
        if ($case->message_id !== $message->id) {
            $errors[] = 'Case message linkage failed';
        } else {
            $this->info("  OK  Case linked to message_id={$message->id}");
        }

        if ($case->category !== 'movie_request') {
            $errors[] = "Expected category='movie_request', got '{$case->category}'";
        } else {
            $this->info("  OK  Category={$case->category}");
        }

        if ($case->status !== 'open') {
            $errors[] = "Expected status='open', got '{$case->status}'";
        } else {
            $this->info("  OK  Status={$case->status}");
        }

        if ($case->source_text !== 'ဒီကားတင်ပေးပါ') {
            $errors[] = "Source text mismatch";
        } else {
            $this->info("  OK  Source text preserved");
        }

        if ($case->platform !== 'telegram') {
            $errors[] = "Expected platform='telegram', got '{$case->platform}'";
        } else {
            $this->info("  OK  Platform={$case->platform}");
        }

        if ($case->customer?->platform_user_id !== 'case-smoke-user') {
            $errors[] = 'Expected customer platform_user_id to be case-smoke-user';
        } else {
            $this->info("  OK  Customer linkage preserved");
        }
    }
}
