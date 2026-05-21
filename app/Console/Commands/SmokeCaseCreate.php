<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SupportCase;
use App\Services\Support\ConversationService;
use App\Services\Support\SupportCaseService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SmokeCaseCreate extends Command
{
    protected $signature = 'smoke:case-create';

    protected $description = 'Smoke test for creating a support case from a customer message';

    public function handle(
        ConversationService $conversationService,
        SupportCaseService $supportCaseService,
        TelegramBotService $botService,
    ): int {
        $this->info('Case Create Smoke Test');
        $this->info('======================');
        $this->newLine();

        $errors = [];

        putenv('TELEGRAM_BOT_TOKEN=smoke-token');
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'smoke-token';
        $_SERVER['TELEGRAM_BOT_TOKEN'] = 'smoke-token';
        Http::fake(fn () => Http::response(['ok' => true, 'result' => []], 200));

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

            $case = $supportCaseService->createFromConversationSelection($conversation, $message->id, [
                'category' => 'movie_request',
                'title' => 'Movie request smoke',
                'description' => 'Smoke test case',
                'priority' => 'normal',
                'status' => 'open',
                'admin_note' => 'Smoke note',
            ]);

            $this->assertCase($case, $message, $errors);

            $caseUpdateOk = $supportCaseService->deliverCustomerUpdate(
                $case,
                'resolved',
                'တောင်းထားတဲ့အကြောင်းအရာကို ဆောင်ရွက်ပြီးပါပြီ။ ကျေးဇူးတင်ပါတယ်။',
                $conversationService,
                $botService,
            );

            if (! $caseUpdateOk) {
                $errors[] = 'Case resolve update failed';
            }

            $rejectCase = $supportCaseService->createFromConversationSelection($conversation, $imageMessage->id, [
                'category' => 'complaint',
                'title' => 'Complaint smoke',
                'description' => 'Smoke rejection case',
                'priority' => 'normal',
                'status' => 'open',
            ]);

            $rejectUpdateOk = $supportCaseService->deliverCustomerUpdate(
                $rejectCase,
                'rejected',
                'တောင်းထားတဲ့အကြောင်းအရာကို လက်ရှိ မရနိုင်သေးပါ။ အဆင်မပြေမှုအတွက် တောင်းပန်ပါတယ်။',
                $conversationService,
                $botService,
            );

            if (! $rejectUpdateOk) {
                $errors[] = 'Case reject update failed';
            }

            $this->assertConversationWorkflowUnchanged($conversation, $errors);
            $this->assertCaseUpdateMessages($conversation, $errors);
            $this->assertRejectCase($rejectCase, $imageMessage, $errors);
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
}
