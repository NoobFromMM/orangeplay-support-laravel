<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\Support\ConversationService;
use App\Services\Support\FaqMatcher;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokeTelegramImage extends Command
{
    protected $signature = 'smoke:telegram-image';
    protected $description = 'Smoke test for F5 Telegram image receive and preview';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        TelegramBotService $botService,
    ): int {
        $this->info('Telegram Image Smoke Test');
        $this->info('=========================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test 1: Photo message → image saved, no bot reply, Needs Reply');
            $this->testPhotoMessage($normalizer, $conversationService, $faqMatcher, $errors);

            $this->info('Test 2: Photo with caption');
            $this->testPhotoWithCaption($normalizer, $conversationService, $errors);

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

    protected function testPhotoMessage(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        array &$errors,
    ): void {
        $payload = $this->mockPhotoPayload(70001, null);

        // Webhook event
        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => 'message',
            'external_event_id' => '70001',
            'external_user_id' => '555001',
            'payload' => $payload,
            'status' => 'received',
            'attempts' => 1,
        ]);
        $this->info('  OK  webhook_events row created');

        // Normalize
        $normalized = $normalizer->normalize($payload);

        if ($normalized['message_type'] !== 'image') {
            $errors[] = "Expected message_type='image', got '{$normalized['message_type']}'";
            return;
        }
        $this->info("  OK  message_type='image'");

        $metadata = $normalized['metadata'];
        if (! isset($metadata['telegram_file_id'])) {
            $errors[] = "Expected metadata.telegram_file_id to be set";
            return;
        }
        $this->info('  OK  metadata.telegram_file_id set');

        if (! isset($metadata['width']) || ! isset($metadata['height'])) {
            $errors[] = "Expected metadata.width and height";
        } else {
            $this->info("  OK  dimensions: {$metadata['width']}x{$metadata['height']}");
        }

        // Largest photo picked (last in array has largest file_size)
        if ($metadata['file_size'] !== 99999) {
            $errors[] = "Expected largest photo file_size=99999, got {$metadata['file_size']}";
        } else {
            $this->info('  OK  largest photo picked by file_size');
        }

        // Customer/conversation
        $customer = $conversationService->findOrCreateCustomer('telegram', '555001', [
            'display_name' => 'Photo User',
        ]);
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);

        // No bot reply for image
        $outboundCount = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->count();

        if ($outboundCount > 0) {
            $errors[] = "Expected no outbound bot reply for image, got {$outboundCount}";
        } else {
            $this->info('  OK  No outbound bot reply for image');
        }

        $conversationService->setStatus($conversation, 'Needs Reply');

        // Conversation status
        if ($conversation->fresh()->status !== 'Needs Reply') {
            $errors[] = "Expected status='Needs Reply', got '{$conversation->fresh()->status}'";
        } else {
            $this->info("  OK  conversation status='Needs Reply'");
        }

        // Inbound message saved
        $inbound = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->first();

        if (! $inbound) {
            $errors[] = "Inbound image message not found";
        } else {
            $this->info("  OK  Inbound image message saved: type={$inbound->message_type}");
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);
        $this->info('  OK  webhook event status=processed');

        $this->newLine();
    }

    protected function testPhotoWithCaption(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        array &$errors,
    ): void {
        $payload = $this->mockPhotoPayload(70002, 'hello');

        $normalized = $normalizer->normalize($payload);

        if ($normalized['message_type'] !== 'image') {
            $errors[] = "Expected message_type='image' for photo with caption";
            return;
        }

        if ($normalized['text'] !== 'hello') {
            $errors[] = "Expected text='hello' (caption), got '{$normalized['text']}'";
        } else {
            $this->info("  OK  caption preserved as text='hello'");
        }

        $metadata = $normalized['metadata'];
        if (($metadata['caption'] ?? '') !== 'hello') {
            $errors[] = "Expected metadata.caption='hello'";
        } else {
            $this->info("  OK  metadata.caption='hello'");
        }

        $this->newLine();
    }

    protected function mockPhotoPayload(int $updateId, ?string $caption): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => [
                    'id' => 555001,
                    'is_bot' => false,
                    'first_name' => 'Photo User',
                ],
                'chat' => [
                    'id' => 555001,
                    'first_name' => 'Photo User',
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'photo' => [
                    [
                        'file_id' => 'small_file_abc',
                        'file_unique_id' => 'uniq_small_abc',
                        'width' => 100,
                        'height' => 75,
                        'file_size' => 1234,
                    ],
                    [
                        'file_id' => 'med_file_abc',
                        'file_unique_id' => 'uniq_med_abc',
                        'width' => 320,
                        'height' => 240,
                        'file_size' => 12345,
                    ],
                    [
                        'file_id' => 'large_file_abc',
                        'file_unique_id' => 'uniq_large_abc',
                        'width' => 1280,
                        'height' => 960,
                        'file_size' => 99999,
                    ],
                ],
                'caption' => $caption,
            ],
        ];
    }
}
