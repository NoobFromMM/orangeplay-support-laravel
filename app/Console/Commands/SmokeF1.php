<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Support\ConversationService;
use App\Services\Support\GreetingMatcher;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramUpdateNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SmokeF1 extends Command
{
    protected $signature = 'smoke:f1';
    protected $description = 'Smoke test for F1 Telegram greeting flow';

    public function handle(
        TelegramUpdateNormalizer $normalizer,
        ConversationService $conversationService,
        GreetingMatcher $greetingMatcher,
        TelegramBotService $botService,
    ): int {
        $this->info('F1 Smoke Test — Telegram Greeting Flow');
        $this->info('====================================');
        $this->newLine();

        $errors = [];

        DB::beginTransaction();

        try {
            $payload = $this->mockTelegramPayload('hi');

            $normalized = $normalizer->normalize($payload);

            $customer = $conversationService->findOrCreateCustomer(
                $normalized['platform'],
                $normalized['platform_user_id'],
                [
                    'display_name' => $normalized['display_name'],
                    'username' => $normalized['username'],
                ]
            );

            $conversation = $conversationService->findOrCreateConversation($customer);

            $conversationService->saveInboundMessage($conversation, $normalized);

            if ($greetingMatcher->isGreeting($normalized['text'])) {
                $replyText = $greetingMatcher->getReplyText();

                $conversationService->saveOutboundMessage(
                    $conversation,
                    $normalized['platform'],
                    $replyText
                );

                $conversationService->setStatus($conversation, 'resolved');
            }

            $this->runAssertions($customer, $conversation, $normalized, $errors);

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

    protected function mockTelegramPayload(string $text): array
    {
        return [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1001,
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'username' => 'testuser',
                    'language_code' => 'en',
                ],
                'chat' => [
                    'id' => 987654321,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'username' => 'testuser',
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }

    protected function runAssertions(
        Customer $customer,
        Conversation $conversation,
        array $normalized,
        array &$errors,
    ): void {
        if ($customer->platform !== 'telegram') {
            $errors[] = "Expected platform='telegram', got '{$customer->platform}'";
        } else {
            $this->info("  OK  Customer created: platform={$customer->platform}");
        }

        if ($customer->platform_user_id !== (string) $normalized['platform_user_id']) {
            $errors[] = "Customer platform_user_id mismatch";
        } else {
            $this->info('  OK  Customer platform_user_id correct');
        }

        if ($customer->display_name !== 'Test User') {
            $errors[] = "Expected display_name='Test User', got '{$customer->display_name}'";
        } else {
            $this->info("  OK  Customer display_name='{$customer->display_name}'");
        }

        if ($conversation->customer_id !== $customer->id) {
            $errors[] = "Conversation customer_id mismatch";
        } else {
            $this->info('  OK  Conversation linked to customer');
        }

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($messages->count() !== 2) {
            $errors[] = "Expected 2 messages, got {$messages->count()}";
        } else {
            $this->info("  OK  2 messages saved (inbound + outbound)");
        }

        $inbound = $messages->firstWhere('direction', 'inbound');
        if (! $inbound) {
            $errors[] = "Inbound message not found";
        } else {
            $this->info("  OK  Inbound message saved: text='{$inbound->text}'");
        }

        $outbound = $messages->firstWhere('direction', 'outbound');
        if (! $outbound) {
            $errors[] = "Outbound message not found";
        } else {
            $expectedReply = "မင်္ဂလာပါရှင့် Orange Play Customer Service မှကြိုဆိုပါတယ်။ဘာများကူညီပေးရမလဲရှင့်";
            if ($outbound->text !== $expectedReply) {
                $errors[] = "Outbound reply text does not match expected";
            } else {
                $this->info('  OK  Outbound reply text matches expected');
            }

            if (str_contains((string) $outbound->text, 'OrangePlayAI')) {
                $errors[] = "Outbound reply contains banned phrase 'OrangePlayAI'";
            } else {
                $this->info("  OK  Reply does NOT contain 'OrangePlayAI'");
            }

            if (str_contains((string) $outbound->text, 'Support Bot')) {
                $errors[] = "Outbound reply contains banned phrase 'Support Bot'";
            } else {
                $this->info("  OK  Reply does NOT contain 'Support Bot'");
            }
        }

        if ($messages[0]->id >= $messages[1]->id) {
            $errors[] = "Message order should be id ASC";
        } else {
            $this->info('  OK  Message order is id ASC');
        }

        if ($conversation->status !== 'resolved') {
            $errors[] = "Expected status='resolved', got '{$conversation->status}'";
        } else {
            $this->info("  OK  Conversation status is 'resolved'");
        }

        $this->newLine();
    }
}
