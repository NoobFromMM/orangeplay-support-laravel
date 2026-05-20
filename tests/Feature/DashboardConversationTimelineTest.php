<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardConversationTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_detail_shows_all_messages_in_chronological_order(): void
    {
        $customer = Customer::create([
            'platform' => 'telegram',
            'platform_user_id' => 'timeline-user',
            'display_name' => 'Timeline User',
            'username' => 'timelineuser',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'in_chat',
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'text' => 'Customer hello',
            'raw_payload' => ['message_id' => 1],
            'metadata' => ['source' => 'telegram'],
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'image',
            'text' => null,
            'raw_payload' => ['message_id' => 2],
            'metadata' => [
                'telegram_file_id' => 'file_001',
                'telegram_file_unique_id' => 'unique_001',
                'width' => 1280,
                'height' => 960,
            ],
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => 'Bot reply',
            'raw_payload' => null,
            'metadata' => ['source' => 'faq'],
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'outbound',
            'sender_type' => 'admin',
            'message_type' => 'text',
            'text' => 'Admin reply',
            'raw_payload' => null,
            'metadata' => ['source' => 'dashboard'],
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'outbound',
            'sender_type' => 'system',
            'message_type' => 'system',
            'text' => 'System note',
            'raw_payload' => null,
            'metadata' => ['event' => 'system_note'],
        ]);

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}");

        $response->assertOk();
        $response->assertSeeInOrder([
            'Customer hello',
            '/telegram/file/file_001',
            'Bot reply',
            'Admin reply',
            'System note',
        ]);
        $response->assertSee('customer');
        $response->assertSee('bot');
        $response->assertSee('admin');
        $response->assertSee('system');
    }
}
