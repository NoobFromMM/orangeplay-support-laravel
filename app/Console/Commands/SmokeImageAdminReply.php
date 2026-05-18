<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\Support\ConversationService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SmokeImageAdminReply extends Command
{
    protected $signature = 'smoke:image-admin-reply';
    protected $description = 'Regression smoke: image receive + admin reply full flow';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
    ): int {
        $this->info('Image + Admin Reply Regression Smoke');
        $this->info('====================================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('1. Image receive phase');
            $this->testImageReceive($normalizer, $conversationService, $errors);

            $this->info('2. Admin reply phase');
            $this->testAdminReply($conversationService, $errors);

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

    protected function testImageReceive(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $photoPayload = [
            'update_id' => 80001,
            'message' => [
                'message_id' => 80001,
                'from' => ['id' => 777001, 'is_bot' => false, 'first_name' => 'ImageUser', 'username' => 'imageuser'],
                'chat' => ['id' => 777001, 'first_name' => 'ImageUser', 'type' => 'private'],
                'date' => now()->timestamp,
                'photo' => [
                    ['file_id' => 'small_xyz', 'file_unique_id' => 'uniq_small', 'width' => 100, 'height' => 75, 'file_size' => 1000],
                    ['file_id' => 'large_xyz', 'file_unique_id' => 'uniq_large', 'width' => 800, 'height' => 600, 'file_size' => 50000],
                ],
            ],
        ];

        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => 'message',
            'external_event_id' => '80001',
            'external_user_id' => '777001',
            'payload' => $photoPayload,
            'status' => 'received',
            'attempts' => 1,
        ]);
        $this->info('  OK  webhook_events row created');

        $normalized = $normalizer->normalize($photoPayload);

        if ($normalized['message_type'] !== 'image') {
            $errors[] = "Expected message_type=image, got {$normalized['message_type']}";
            return;
        }
        $this->info("  OK  message_type=image");

        $metadata = $normalized['metadata'];
        if (empty($metadata['telegram_file_id'])) {
            $errors[] = "Expected telegram_file_id in metadata";
            return;
        }
        $this->info('  OK  telegram_file_id present');

        $customer = $conversationService->findOrCreateCustomer('telegram', '777001', [
            'display_name' => $normalized['display_name'],
            'username' => $normalized['username'],
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $inboundImage = $conversationService->saveInboundMessage($conversation, $normalized);

        if ($inboundImage->message_type !== 'image') {
            $errors[] = "Expected saved message_type=image, got {$inboundImage->message_type}";
        }
        if ($inboundImage->direction !== 'inbound') {
            $errors[] = "Expected direction=inbound";
        }
        if ($inboundImage->sender_type !== 'customer') {
            $errors[] = "Expected sender_type=customer";
        }
        $this->info('  OK  image message saved: dir=inbound, sender=customer');

        $conversationService->setStatus($conversation, 'Needs Reply');

        if ($conversation->fresh()->status !== 'Needs Reply') {
            $errors[] = "Expected Needs Reply after image, got {$conversation->fresh()->status}";
        } else {
            $this->info("  OK  status='Needs Reply' after image");
        }

        // No bot reply for image
        $botReplies = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'bot')
            ->count();
        if ($botReplies > 0) {
            $errors[] = "Expected no bot reply for image, got {$botReplies}";
        } else {
            $this->info('  OK  no bot auto-reply for image');
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);
        $this->info('  OK  webhook event processed');

        $this->newLine();
    }

    protected function testAdminReply(ConversationService $conversationService, array &$errors): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $customer = $conversationService->findOrCreateCustomer('telegram', '777001', [
            'display_name' => 'ImageUser',
            'username' => 'imageuser',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);

        $botService = app(TelegramBotService::class);
        $sent = $botService->sendMessage('777001', 'Admin reply to your image');

        if (! $sent) {
            $errors[] = "Expected admin Telegram send to succeed";
            return;
        }
        $this->info('  OK  Telegram send faked successfully');

        $conversationService->saveAdminOutboundMessage(
            $conversation,
            'telegram',
            'Admin reply to your image',
            'text',
            ['source' => 'dashboard'],
        );
        $conversationService->setStatus($conversation, 'in_chat');

        $adminMsg = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'admin')
            ->first();

        if (! $adminMsg) {
            $errors[] = "Admin outbound message not saved";
            return;
        }
        $this->info('  OK  admin outbound message saved');

        if ($adminMsg->direction !== 'outbound') {
            $errors[] = "Expected direction=outbound, got {$adminMsg->direction}";
        } else {
            $this->info("  OK  direction=outbound");
        }

        if ($adminMsg->sender_type !== 'admin') {
            $errors[] = "Expected sender_type=admin, got {$adminMsg->sender_type}";
        } else {
            $this->info("  OK  sender_type=admin");
        }

        $meta = $adminMsg->metadata;
        if (($meta['source'] ?? '') !== 'dashboard') {
            $errors[] = "Expected metadata.source=dashboard";
        } else {
            $this->info("  OK  metadata.source=dashboard");
        }

        if ($conversation->fresh()->status !== 'in_chat') {
            $errors[] = "Expected in_chat after admin reply, got {$conversation->fresh()->status}";
        } else {
            $this->info("  OK  status='in_chat' after admin reply");
        }

        // Original image still exists
        $imageMsg = Message::where('conversation_id', $conversation->id)
            ->where('message_type', 'image')
            ->first();
        if (! $imageMsg) {
            $errors[] = "Original image message lost after admin reply";
        } else {
            $this->info('  OK  original image message preserved');
        }

        $imageMeta = $imageMsg->metadata;
        if (empty($imageMeta['telegram_file_id'])) {
            $errors[] = "Original image telegram_file_id lost";
        } else {
            $this->info('  OK  original image telegram_file_id still present');
        }

        // Timeline order
        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $ids = $messages->pluck('id')->toArray();
        $sorted = $ids;
        sort($sorted, SORT_NUMERIC);
        if ($ids !== array_values($sorted)) {
            $errors[] = "Timeline order should be created_at ASC, id ASC";
        } else {
            $this->info('  OK  timeline order correct (created_at ASC, id ASC)');
        }

        // Faked Telegram send verified
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/bot')
                && str_contains($request->url(), '/sendMessage')
                && $request['chat_id'] === '777001'
                && $request['text'] === 'Admin reply to your image';
        });
        $this->info('  OK  Telegram API called with correct chat_id and text');

        $this->newLine();
    }
}
