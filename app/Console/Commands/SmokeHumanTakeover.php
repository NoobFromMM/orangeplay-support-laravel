<?php

namespace App\Console\Commands;

use App\Models\FaqEntry;
use App\Models\Message;
use App\Services\Support\ConversationService;
use App\Services\Support\FaqMatcher;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SmokeHumanTakeover extends Command
{
    protected $signature = 'smoke:human-takeover';
    protected $description = 'Smoke test for bot pause / human takeover conversation workflow';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
    ): int {
        $this->info('Human Takeover Smoke Test');
        $this->info('=========================');
        $this->newLine();

        $errors = [];

        putenv('TELEGRAM_BOT_TOKEN=smoke-token');
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'smoke-token';
        $_SERVER['TELEGRAM_BOT_TOKEN'] = 'smoke-token';

        Http::fake(function () {
            return Http::response(['ok' => true, 'result' => []], 200);
        });

        DB::beginTransaction();

        try {
            $faq = $this->seedFaqEntry();
            $customer = null;
            $conversation = null;

            $this->info('Test 1: FAQ match when bot active');
            [$customer, $conversation] = $this->processText(
                $normalizer,
                $conversationService,
                $faqMatcher,
                'human-takeover-keyword',
                88001,
                $customer,
                $conversation
            );

            $this->assertConversationState($conversation, 'resolved', false, $errors, 'bot active FAQ reply');
            $this->assertBotCount($conversation, 1, $errors, 'bot active FAQ reply');

            $this->info('Test 2: Admin reply enables human takeover');
            $conversationService->saveAdminOutboundMessage(
                $conversation,
                'telegram',
                'Human takeover reply',
                'text',
                ['source' => 'dashboard'],
            );
            $conversation->updateWorkflow('Needs Reply', true);

            $this->assertConversationState($conversation, 'Needs Reply', true, $errors, 'admin reply');
            $this->assertAdminCount($conversation, 1, $errors, 'admin reply');

            $this->info('Test 3: FAQ match while paused should NOT auto reply');
            [$customer, $conversation] = $this->processText(
                $normalizer,
                $conversationService,
                $faqMatcher,
                'human-takeover-keyword',
                88002,
                $customer,
                $conversation
            );

            $this->assertConversationState($conversation, 'Needs Reply', true, $errors, 'paused FAQ');
            $this->assertBotCount($conversation, 1, $errors, 'paused FAQ');

            $this->info('Test 4: Manual resolve clears bot pause');
            $conversation->updateWorkflow('resolved', false);
            $this->assertConversationState($conversation, 'resolved', false, $errors, 'manual resolve');

            $this->info('Test 5: FAQ match after resolve auto replies again');
            [$customer, $conversation] = $this->processText(
                $normalizer,
                $conversationService,
                $faqMatcher,
                'human-takeover-keyword',
                88003,
                $customer,
                $conversation
            );

            $this->assertConversationState($conversation, 'resolved', false, $errors, 'post-resolve FAQ');
            $this->assertBotCount($conversation, 2, $errors, 'post-resolve FAQ');

            $this->info('Test 6: Manual reopen keeps bot paused');
            $conversation->updateWorkflow('Needs Reply', true);
            $this->assertConversationState($conversation, 'Needs Reply', true, $errors, 'manual reopen');

            $this->assertKeywordMatched($faqMatcher, $faq, $errors);
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

    protected function seedFaqEntry(): FaqEntry
    {
        return FaqEntry::updateOrCreate(
            ['intent_code' => 'human_takeover_smoke'],
            [
                'category' => 'testing',
                'keywords' => ['human-takeover-keyword'],
                'answer_text' => 'Human takeover smoke answer',
                'priority' => 999,
                'is_active' => true,
            ]
        );
    }

    protected function processText(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        FaqMatcher $faqMatcher,
        string $text,
        int $userId,
        ?\App\Models\Customer $customer,
        ?\App\Models\Conversation $conversation,
    ): array {
        $payload = $this->mockTelegramPayload($text, $userId);
        $normalized = $normalizer->normalize($payload);

        $customer ??= $conversationService->findOrCreateCustomer(
            $normalized['platform'],
            $normalized['platform_user_id'],
            [
                'display_name' => $normalized['display_name'],
                'username' => $normalized['username'],
            ]
        );

        $conversation ??= $conversationService->findOrCreateConversation($customer);
        $conversationService->saveInboundMessage($conversation, $normalized);

        if ($conversation->isBotPaused()) {
            $conversation->updateWorkflow('Needs Reply');
            return [$customer, $conversation];
        }

        $matchedEntry = $faqMatcher->match($normalized['text']);

        if ($matchedEntry) {
            app(TelegramBotService::class)->sendMessage($normalized['platform_user_id'], $matchedEntry->answer_text);
            $conversationService->saveOutboundMessage($conversation, $normalized['platform'], $matchedEntry->answer_text);
            $conversation->updateWorkflow('resolved', false);
        } else {
            $conversation->updateWorkflow('Needs Reply', false);
        }

        return [$customer, $conversation];
    }

    protected function assertConversationState(
        $conversation,
        string $expectedStatus,
        bool $expectedPaused,
        array &$errors,
        string $label,
    ): void {
        $fresh = $conversation->fresh();

        if ($fresh->status !== $expectedStatus) {
            $errors[] = "{$label} — expected status='{$expectedStatus}', got '{$fresh->status}'";
        } else {
            $this->info("  OK  {$label}: status='{$expectedStatus}'");
        }

        if ((bool) $fresh->bot_paused !== $expectedPaused) {
            $errors[] = "{$label} — expected bot_paused=" . ($expectedPaused ? 'true' : 'false') . ", got " . ((bool) $fresh->bot_paused ? 'true' : 'false');
        } else {
            $this->info("  OK  {$label}: bot_paused=" . ($expectedPaused ? 'true' : 'false'));
        }
    }

    protected function assertBotCount($conversation, int $expected, array &$errors, string $label): void
    {
        $count = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'bot')
            ->count();

        if ($count !== $expected) {
            $errors[] = "{$label} — expected {$expected} bot messages, got {$count}";
        } else {
            $this->info("  OK  {$label}: bot message count={$expected}");
        }
    }

    protected function assertAdminCount($conversation, int $expected, array &$errors, string $label): void
    {
        $count = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'admin')
            ->count();

        if ($count !== $expected) {
            $errors[] = "{$label} — expected {$expected} admin messages, got {$count}";
        } else {
            $this->info("  OK  {$label}: admin message count={$expected}");
        }
    }

    protected function assertKeywordMatched(FaqMatcher $faqMatcher, FaqEntry $faq, array &$errors): void
    {
        $matched = $faqMatcher->match('human-takeover-keyword');

        if (! $matched || $matched->id !== $faq->id) {
            $errors[] = "Expected FAQ matcher to match the seeded entry after resolve";
        } else {
            $this->info('  OK  FAQ matcher still works after bot resume');
        }
    }

    protected function mockTelegramPayload(string $text, int $userId): array
    {
        return [
            'update_id' => 900000 + $userId,
            'message' => [
                'message_id' => $userId,
                'from' => [
                    'id' => $userId,
                    'is_bot' => false,
                    'first_name' => 'Smoke',
                    'last_name' => 'User',
                    'username' => 'smokeuser' . $userId,
                ],
                'chat' => [
                    'id' => $userId,
                    'first_name' => 'Smoke',
                    'last_name' => 'User',
                    'username' => 'smokeuser' . $userId,
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }
}
