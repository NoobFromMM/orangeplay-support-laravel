<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SupportCase;
use App\Services\Support\ConversationService;
use App\Services\Support\SupportCaseService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
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

            $imageMessage = Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'platform' => 'telegram',
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'image',
                'text' => null,
                'raw_payload' => ['message_id' => 9992],
                'metadata' => [
                    'telegram_file_id' => 'file_smoke_001',
                    'telegram_file_unique_id' => 'file_unique_smoke_001',
                    'caption' => 'ဒီပုံထဲကကားရနိုင်မလား',
                ],
            ]);

            $openMessage = Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'platform' => 'telegram',
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'text' => 'Need a live case',
                'raw_payload' => ['message_id' => 9993],
                'metadata' => null,
            ]);

            $case = $supportCaseService->createFromConversationSelection($conversation, $message->id, [
                'category' => 'movie_request',
                'title' => 'Movie request smoke',
                'description' => 'Smoke test case',
                'priority' => 'normal',
                'status' => 'open',
                'admin_note' => 'Smoke note',
            ]);

            $activeCase = $supportCaseService->createFromConversationSelection($conversation, $openMessage->id, [
                'category' => 'complaint',
                'title' => 'Active case smoke',
                'description' => 'Open active case',
                'priority' => 'high',
                'status' => 'open',
            ]);

            $this->assertCase($case, $message, $errors);
            $this->assertActiveCase($activeCase, $openMessage, $errors);

            // Resolve the original case, reject the active case
            $case->update(['status' => 'resolved', 'resolved_at' => now()]);

            $activeCase->update(['status' => 'rejected', 'resolved_at' => now()]);

            // Create admin update messages (normally created by deliverCustomerUpdate)
            Message::create([
                'conversation_id' => $conversation->id, 'customer_id' => $customer->id,
                'platform' => 'telegram', 'direction' => 'outbound', 'sender_type' => 'admin',
                'message_type' => 'text',
                'text' => 'တောင်းထားတဲ့အကြောင်းအရာကို ဆောင်ရွက်ပြီးပါပြီ။ ကျေးဇူးတင်ပါတယ်။',
                'metadata' => ['source' => 'dashboard', 'event' => 'case_resolved', 'case_id' => $case->id],
            ]);

            Message::create([
                'conversation_id' => $conversation->id, 'customer_id' => $customer->id,
                'platform' => 'telegram', 'direction' => 'outbound', 'sender_type' => 'admin',
                'message_type' => 'text',
                'text' => 'တောင်းထားတဲ့အကြောင်းအရာကို လက်ရှိ မရနိုင်သေးပါ။ အဆင်မပြေမှုအတွက် တောင်းပန်ပါတယ်။',
                'metadata' => ['source' => 'dashboard', 'event' => 'case_rejected', 'case_id' => $activeCase->id],
            ]);

            $this->assertConversationWorkflowUnchanged($conversation, $errors);
            $this->assertCaseUpdateMessages($conversation, $errors);

            $request = Request::create("/customers/telegram/{$customer->platform_user_id}", 'GET');
            $response = app()->handle($request);
            $content = $response->getContent();

            // Verify timeline cards are rendered (check for case event labels)
            foreach (['Case Created', 'Case Resolved', 'Case Rejected'] as $needle) {
                if (! str_contains($content, $needle)) {
                    $errors[] = "Conversation timeline missing {$needle} card";
                } else {
                    $this->info("  OK  {$needle} card rendered in conversation timeline");
                }
            }
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

    protected function assertConversationWorkflowUnchanged(Conversation $conversation, array &$errors): void
    {
        $fresh = $conversation->fresh();

        if ($fresh->status !== 'Needs Reply') {
            $errors[] = "Conversation status changed unexpectedly: '{$fresh->status}'";
        } else {
            $this->info("  OK  Conversation status unchanged: {$fresh->status}");
        }

        if (! (bool) $fresh->bot_paused) {
            $errors[] = 'Conversation bot_paused changed unexpectedly';
        } else {
            $this->info('  OK  Conversation bot_paused unchanged');
        }
    }

    protected function assertCaseUpdateMessages(Conversation $conversation, array &$errors): void
    {
        $adminMessages = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'admin')
            ->where('direction', 'outbound')
            ->get();

        if ($adminMessages->count() < 2) {
            $errors[] = 'Expected two admin update messages after resolve/reject';
        } else {
            $this->info('  OK  Resolve/reject customer updates saved to timeline');
        }
    }

    protected function assertRejectCase(SupportCase $case, Message $message, array &$errors): void
    {
        if ($case->message_id !== $message->id) {
            $errors[] = 'Reject case source message linkage failed';
        } else {
            $this->info("  OK  Reject case linked to image message_id={$message->id}");
        }

        if ($case->status !== 'rejected') {
            $errors[] = "Expected rejected case status='rejected', got '{$case->status}'";
        } else {
            $this->info('  OK  Reject case status=rejected');
        }
    }

    protected function assertActiveCase(SupportCase $case, Message $message, array &$errors): void
    {
        if ($case->message_id !== $message->id) {
            $errors[] = 'Active case source message linkage failed';
        } else {
            $this->info("  OK  Active case linked to message_id={$message->id}");
        }

        if (! $case->isActive()) {
            $errors[] = "Expected active case to remain active, got '{$case->status}'";
        } else {
            $this->info("  OK  Active case status={$case->status}");
        }
    }
}
