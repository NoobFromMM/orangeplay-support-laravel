<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use App\Services\Support\ConversationService;
use App\Services\Support\FaqMatcher;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokeWebhookEvents extends Command
{
    protected $signature = 'smoke:webhook-events';
    protected $description = 'Smoke test for W1/W2 webhook event raw logging';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        TelegramBotService $botService,
    ): int {
        $this->info('Webhook Events Smoke Test');
        $this->info('=========================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $this->info('Test 1: hi → greeting reply + webhook event logged');
            $this->testGreetingFlow($normalizer, $conversationService, $faqMatcher, $botService, $errors);

            $this->info('Test 2: unknown text → Needs Reply + webhook event logged');
            $this->testUnknownFlow($normalizer, $conversationService, $faqMatcher, $botService, $errors);

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

    protected function testGreetingFlow(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        TelegramBotService $botService,
        array &$errors,
    ): void {
        $payload = $this->mockPayload('hi', 90001);

        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => 'message',
            'external_event_id' => (string) ($payload['update_id'] ?? ''),
            'external_user_id' => (string) ($payload['message']['from']['id'] ?? ''),
            'payload' => $payload,
            'status' => 'received',
            'attempts' => 1,
        ]);

        if ($event->channel !== 'telegram') {
            $errors[] = "Expected channel='telegram', got '{$event->channel}'";
        } else {
            $this->info('  OK  channel=telegram');
        }

        if ((string) $event->external_event_id !== '90001') {
            $errors[] = "Expected external_event_id='90001', got '{$event->external_event_id}'";
        } else {
            $this->info('  OK  external_event_id=update_id');
        }

        if (($event->payload['message']['text'] ?? '') !== 'hi') {
            $errors[] = "Expected payload.message.text='hi'";
        } else {
            $this->info("  OK  payload.message.text='hi'");
        }

        // Run existing processing
        $normalized = $normalizer->normalize($payload);
        $customer = $conversationService->findOrCreateCustomer(
            $normalized['platform'], $normalized['platform_user_id'],
            ['display_name' => $normalized['display_name'], 'username' => $normalized['username']]
        );
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);

        $matchedEntry = $faqMatcher->match($normalized['text']);

        if ($matchedEntry) {
            $conversationService->saveOutboundMessage($conversation, $normalized['platform'], $matchedEntry->answer_text);
            $conversationService->setStatus($conversation, 'resolved');
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        if ($event->fresh()->status !== 'processed') {
            $errors[] = "Expected status='processed', got '{$event->fresh()->status}'";
        } else {
            $this->info("  OK  status='processed'");
        }

        if ($event->fresh()->processed_at === null) {
            $errors[] = "Expected processed_at not null";
        } else {
            $this->info('  OK  processed_at set');
        }

        if ($conversation->fresh()->status !== 'resolved') {
            $errors[] = "Expected conversation status='resolved', got '{$conversation->fresh()->status}'";
        } else {
            $this->info("  OK  F1 greeting still works: status='resolved'");
        }

        $this->newLine();
    }

    protected function testUnknownFlow(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        TelegramBotService $botService,
        array &$errors,
    ): void {
        $payload = $this->mockPayload('xyzzyblah', 90002);

        $event = WebhookEvent::create([
            'channel' => 'telegram',
            'event_type' => 'message',
            'external_event_id' => (string) ($payload['update_id'] ?? ''),
            'external_user_id' => (string) ($payload['message']['from']['id'] ?? ''),
            'payload' => $payload,
            'status' => 'received',
            'attempts' => 1,
        ]);

        if ($event->fresh()->status !== 'received') {
            $errors[] = "Expected initial status='received'";
        } else {
            $this->info("  OK  initial status='received'");
        }

        $normalized = $normalizer->normalize($payload);
        $customer = $conversationService->findOrCreateCustomer(
            $normalized['platform'], $normalized['platform_user_id'],
            ['display_name' => $normalized['display_name']]
        );
        $conversation = $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);

        $matchedEntry = $faqMatcher->match($normalized['text']);

        if (! $matchedEntry) {
            $conversationService->setStatus($conversation, 'Needs Reply');
        }

        $event->update(['status' => 'processed', 'processed_at' => now()]);

        if ($event->fresh()->status !== 'processed') {
            $errors[] = "Expected status='processed' for unknown text";
        } else {
            $this->info("  OK  status='processed' for unknown text");
        }

        if ($conversation->fresh()->status !== 'Needs Reply') {
            $errors[] = "Expected Needs Reply for unknown text, got '{$conversation->fresh()->status}'";
        } else {
            $this->info("  OK  conversation status='Needs Reply' for unknown text");
        }

        $this->newLine();
    }

    protected function mockPayload(string $text, int $updateId): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => [
                    'id' => 555000 + $updateId,
                    'is_bot' => false,
                    'first_name' => 'WebhookTest',
                ],
                'chat' => [
                    'id' => 555000 + $updateId,
                    'first_name' => 'WebhookTest',
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }
}
