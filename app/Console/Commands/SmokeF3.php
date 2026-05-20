<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\Support\ConversationService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SmokeF3 extends Command
{
    protected $signature = 'smoke:f3';
    protected $description = 'Smoke test for F3 Admin Reply from Dashboard to Telegram';

    public function handle(
        ConversationService $conversationService,
    ): int {
        $this->info('F3 Smoke Test — Admin Reply');
        $this->info('===========================');
        $this->newLine();

        Http::fake(function ($request) {
            if (isset($request['chat_id']) && $request['chat_id'] === '555000') {
                return Http::response(['ok' => true, 'result' => []], 200);
            }
            if (isset($request['chat_id'])) {
                return Http::response(['ok' => false, 'description' => 'Forbidden'], 403);
            }
            return Http::response(['ok' => false], 503);
        });

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test 1: Successful admin reply');
            $this->testSuccessfulReply($conversationService, $errors);

            $this->info('Test 2: Failed Telegram send');
            $this->testFailedTelegramSend($conversationService, $errors);

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

    protected function testSuccessfulReply(ConversationService $conversationService, array &$errors): void
    {
        $customer = $conversationService->findOrCreateCustomer('telegram', '555000', [
            'display_name' => 'Admin Test User',
            'username' => 'admintest',
        ]);

        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversation->updateWorkflow('Needs Reply', false);

        $botService = app(TelegramBotService::class);
        $sent = $botService->sendMessage('555000', 'Hello from admin');

        if (! $sent) {
            $errors[] = "Expected Telegram send to succeed";
            return;
        }
        $this->info('  OK  Telegram send succeeded');

        $conversationService->saveAdminOutboundMessage(
            $conversation,
            'telegram',
            'Hello from admin',
            'text',
            ['source' => 'dashboard'],
        );
        $conversation->updateWorkflow('Needs Reply', true);

        $adminMessage = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'admin')
            ->first();

        if (! $adminMessage) {
            $errors[] = "Admin outbound message not found in DB";
            return;
        }
        $this->info('  OK  Admin outbound message saved');

        if ($adminMessage->direction !== 'outbound') {
            $errors[] = "Expected direction=outbound, got '{$adminMessage->direction}'";
        } else {
            $this->info("  OK  direction='outbound'");
        }

        if ($adminMessage->sender_type !== 'admin') {
            $errors[] = "Expected sender_type=admin, got '{$adminMessage->sender_type}'";
        } else {
            $this->info("  OK  sender_type='admin'");
        }

        $metadata = $adminMessage->metadata;
        if (! is_array($metadata) || ($metadata['source'] ?? '') !== 'dashboard') {
            $errors[] = "Expected metadata.source='dashboard'";
        } else {
            $this->info("  OK  metadata.source='dashboard'");
        }

        if ($conversation->fresh()->status !== 'Needs Reply') {
            $errors[] = "Expected status to remain 'Needs Reply', got '{$conversation->fresh()->status}'";
        } else {
            $this->info("  OK  conversation status remains 'Needs Reply'");
        }

        if (! $conversation->fresh()->bot_paused) {
            $errors[] = "Expected bot_paused=true after admin reply";
        } else {
            $this->info('  OK  bot_paused=true after admin reply');
        }

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $ids = $messages->pluck('id')->toArray();
        $sorted = $ids;
        sort($sorted, SORT_NUMERIC);
        if ($ids !== array_values($sorted)) {
            $errors[] = "Message order should be created_at ASC, id ASC";
        } else {
            $this->info('  OK  Timeline order correct');
        }

        $this->newLine();
    }

    protected function testFailedTelegramSend(ConversationService $conversationService, array &$errors): void
    {
        $customer = $conversationService->findOrCreateCustomer('telegram', '666000', [
            'display_name' => 'Fail Test User',
        ]);

        $conversation = $conversationService->findOrCreateConversation($customer);

        $botService = app(TelegramBotService::class);
        $sent = $botService->sendMessage('666000', 'Should fail');

        if ($sent) {
            $errors[] = "Expected Telegram send to fail for 403 response (got true)";
        } else {
            $this->info('  OK  Telegram send correctly returned false for 403');
        }

        $adminMessages = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'admin')
            ->count();

        if ($adminMessages > 0) {
            $errors[] = "Expected no admin message saved on send failure, found {$adminMessages}";
        } else {
            $this->info('  OK  No admin message saved on send failure');
        }

        $this->newLine();
    }
}
