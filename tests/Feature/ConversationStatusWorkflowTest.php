<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_resolve_and_reopen_update_conversation_status(): void
    {
        $customer = Customer::create([
            'platform' => 'telegram',
            'platform_user_id' => 'status-user',
            'display_name' => 'Status User',
            'username' => 'statususer',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'Needs Reply',
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'text' => 'Customer needs help',
            'raw_payload' => ['message_id' => 1],
            'metadata' => null,
        ]);

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}");
        $response->assertOk();
        $response->assertSee('Resolve');

        $resolveResponse = $this->post("/customers/telegram/{$customer->platform_user_id}/resolve");
        $resolveResponse->assertRedirect();
        $this->assertSame('resolved', $conversation->fresh()->status);

        $resolvedPage = $this->get("/customers/telegram/{$customer->platform_user_id}");
        $resolvedPage->assertOk();
        $resolvedPage->assertSee('Reopen');

        $resolvedDashboard = $this->get('/dashboard?filter=resolved');
        $resolvedDashboard->assertOk();
        $resolvedDashboard->assertSee('Status User');

        $reopenResponse = $this->post("/customers/telegram/{$customer->platform_user_id}/reopen");
        $reopenResponse->assertRedirect();
        $this->assertSame('Needs Reply', $conversation->fresh()->status);

        $reopenedPage = $this->get("/customers/telegram/{$customer->platform_user_id}");
        $reopenedPage->assertOk();
        $reopenedPage->assertSee('Resolve');

        $needsReplyDashboard = $this->get('/dashboard?filter=needs_reply');
        $needsReplyDashboard->assertOk();
        $needsReplyDashboard->assertSee('Status User');
    }

    public function test_admin_reply_does_not_auto_change_conversation_status(): void
    {
        $customer = Customer::create([
            'platform' => 'telegram',
            'platform_user_id' => 'reply-user',
            'display_name' => 'Reply User',
            'username' => 'replyuser',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'Needs Reply',
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'text' => 'Need human help',
            'raw_payload' => ['message_id' => 1],
            'metadata' => null,
        ]);

        app()->instance(TelegramBotService::class, new class extends TelegramBotService {
            public function sendMessage(string $chatId, string $text): bool
            {
                return true;
            }
        });

        $response = $this->post("/customers/telegram/{$customer->platform_user_id}/reply", [
            'message' => 'Hello from admin',
        ]);

        $response->assertRedirect();
        $this->assertSame('Needs Reply', $conversation->fresh()->status);

        $adminMessage = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'admin')
            ->first();

        $this->assertNotNull($adminMessage);
        $this->assertSame('Hello from admin', $adminMessage?->text);
        $this->assertSame('outbound', $adminMessage?->direction);
    }
}
