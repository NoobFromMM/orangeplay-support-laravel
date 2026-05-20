<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FaqEntry;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HumanTakeoverBotPauseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reply_pauses_bot_until_manual_resolve(): void
    {
        $this->setTelegramToken();
        Http::fake(fn () => Http::response(['ok' => true, 'result' => []], 200));

        FaqEntry::create([
            'intent_code' => 'human_takeover_route_test',
            'category' => 'testing',
            'keywords' => ['pause-bot-test'],
            'answer_text' => 'Auto reply from FAQ',
            'priority' => 999,
            'is_active' => true,
        ]);

        $userId = 'pause-bot-user';

        $this->postJson('/webhooks/telegram', $this->telegramPayload('pause-bot-test', $userId))
            ->assertOk();

        $customer = Customer::where('platform', 'telegram')
            ->where('platform_user_id', $userId)
            ->firstOrFail();

        $conversation = Conversation::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('resolved', $conversation->status);
        $this->assertFalse((bool) $conversation->bot_paused);
        $this->assertSame(2, Message::where('conversation_id', $conversation->id)->count());
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('sender_type', 'bot')->count());

        $this->post("/customers/telegram/{$userId}/reply", [
            'message' => 'Human reply',
        ])->assertRedirect();

        $conversation->refresh();
        $this->assertSame('Needs Reply', $conversation->status);
        $this->assertTrue((bool) $conversation->bot_paused);
        $this->assertSame(2, Message::where('conversation_id', $conversation->id)->where('direction', 'outbound')->count());
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('sender_type', 'bot')->count());

        $this->postJson('/webhooks/telegram', $this->telegramPayload('pause-bot-test', $userId))
            ->assertOk();

        $conversation->refresh();
        $this->assertSame('Needs Reply', $conversation->status);
        $this->assertTrue((bool) $conversation->bot_paused);
        $this->assertSame(4, Message::where('conversation_id', $conversation->id)->count());
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('sender_type', 'bot')->count());

        $this->post("/customers/telegram/{$userId}/resolve")->assertRedirect();
        $conversation->refresh();
        $this->assertSame('resolved', $conversation->status);
        $this->assertFalse((bool) $conversation->bot_paused);

        $this->postJson('/webhooks/telegram', $this->telegramPayload('pause-bot-test', $userId))
            ->assertOk();

        $conversation->refresh();
        $this->assertSame('resolved', $conversation->status);
        $this->assertFalse((bool) $conversation->bot_paused);
        $this->assertSame(6, Message::where('conversation_id', $conversation->id)->count());
        $this->assertSame(2, Message::where('conversation_id', $conversation->id)->where('sender_type', 'bot')->count());

        $this->post("/customers/telegram/{$userId}/reopen")->assertRedirect();
        $conversation->refresh();
        $this->assertSame('Needs Reply', $conversation->status);
        $this->assertTrue((bool) $conversation->bot_paused);
    }

    protected function telegramPayload(string $text, string $userId): array
    {
        return [
            'update_id' => random_int(100000, 999999),
            'message' => [
                'message_id' => random_int(1000, 9999),
                'from' => [
                    'id' => $userId,
                    'is_bot' => false,
                    'first_name' => 'Pause',
                    'last_name' => 'Bot',
                    'username' => 'pausebot',
                ],
                'chat' => [
                    'id' => $userId,
                    'first_name' => 'Pause',
                    'last_name' => 'Bot',
                    'username' => 'pausebot',
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }

    protected function setTelegramToken(): void
    {
        putenv('TELEGRAM_BOT_TOKEN=test-token');
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'test-token';
        $_SERVER['TELEGRAM_BOT_TOKEN'] = 'test-token';
    }
}
