<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SupportCase;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportCaseCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_page_shows_one_create_case_button_in_header(): void
    {
        [$customer] = $this->seedConversationWithMessages();

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}");

        $response->assertOk();
        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'Create Case'));
        $this->assertStringNotContainsString('/messages/', $content);
    }

    public function test_create_case_form_lists_recent_inbound_messages_and_defaults_latest(): void
    {
        [$customer, $conversation, $textMessage, $imageMessage, $fileMessage] = $this->seedConversationWithMessages();

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}/cases/create");

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Text message', $content);
        $this->assertStringContainsString('Image message', $content);
        $this->assertStringContainsString('File message', $content);
        $this->assertStringContainsString('ဒီကားတင်ပေးပါ', $content);
        $this->assertStringContainsString('caption for image', $content);
        $this->assertStringContainsString('caption for file', $content);
        $this->assertStringContainsString('value="' . $fileMessage->id . '" checked', $content);
    }

    public function test_admin_can_create_case_from_conversation_and_keep_chat_state_unchanged(): void
    {
        [$customer, $conversation, $textMessage] = $this->seedConversationWithMessages();

        $response = $this->post("/customers/telegram/{$customer->platform_user_id}/cases", [
            'message_id' => $textMessage->id,
            'category' => 'movie_request',
            'title' => 'Movie request from Telegram',
            'description' => 'Customer requested a title.',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response->assertRedirect();

        $case = SupportCase::latest('id')->first();
        $this->assertNotNull($case);
        $this->assertSame($customer->id, $case->customer_id);
        $this->assertSame($conversation->id, $case->conversation_id);
        $this->assertSame($textMessage->id, $case->message_id);
        $this->assertSame('movie_request', $case->category);
        $this->assertSame('open', $case->status);
        $this->assertSame('high', $case->priority);
        $this->assertSame('ဒီကားတင်ပေးပါ', $case->source_text);
        $this->assertSame('telegram', $case->platform);
        $this->assertSame('Needs Reply', $conversation->fresh()->status);
        $this->assertFalse((bool) $conversation->fresh()->bot_paused);

        $this->get('/cases')->assertOk()->assertSee('Movie request from Telegram');
        $this->get("/customers/telegram/{$customer->platform_user_id}")
            ->assertOk()
            ->assertSee('Active Cases')
            ->assertSee('Movie request from Telegram');
    }

    public function test_admin_can_create_case_from_image_or_file_message(): void
    {
        [$customer, $conversation, $textMessage, $imageMessage] = $this->seedConversationWithMessages();

        $response = $this->post("/customers/telegram/{$customer->platform_user_id}/cases", [
            'message_id' => $imageMessage->id,
            'category' => 'movie_request',
            'title' => 'Image based movie request',
            'description' => 'Customer shared a screenshot.',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $response->assertRedirect();

        $case = SupportCase::latest('id')->first();
        $this->assertNotNull($case);
        $this->assertSame($imageMessage->id, $case->message_id);
        $this->assertSame('caption for image', $case->source_text);
        $this->assertSame('file_image_abc', $case->source_metadata['raw_message_metadata']['telegram_file_id'] ?? null);
    }

    public function test_invalid_selected_source_message_is_rejected(): void
    {
        [$customer, $conversation, $textMessage] = $this->seedConversationWithMessages();

        $otherCustomer = Customer::create([
            'platform' => 'telegram',
            'platform_user_id' => 'other-case-user',
            'display_name' => 'Other Case User',
            'username' => 'othercase',
        ]);

        $otherConversation = Conversation::create([
            'customer_id' => $otherCustomer->id,
            'status' => 'Needs Reply',
            'bot_paused' => false,
            'last_message_at' => now(),
        ]);

        $otherMessage = Message::create([
            'conversation_id' => $otherConversation->id,
            'customer_id' => $otherCustomer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'text' => 'Wrong conversation',
            'raw_payload' => ['message_id' => 998],
            'metadata' => null,
        ]);

        $response = $this->post("/customers/telegram/{$customer->platform_user_id}/cases", [
            'message_id' => $otherMessage->id,
            'category' => 'complaint',
            'title' => 'Bad case',
            'description' => 'Bad case',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $response->assertSessionHasErrors(['message_id']);
        $this->assertSame(0, SupportCase::count());
    }

    public function test_case_show_does_not_display_raw_source_metadata_json(): void
    {
        [$customer, $conversation, $textMessage] = $this->seedConversationWithMessages();

        $case = SupportCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'message_id' => $textMessage->id,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'complaint',
            'title' => 'Case detail',
            'description' => 'Shown in list',
            'status' => 'open',
            'priority' => 'normal',
            'source_text' => 'Case source text',
            'source_metadata' => ['message_id' => $textMessage->id, 'raw_message_metadata' => ['telegram_file_id' => 'hidden']],
        ]);

        $response = $this->get("/cases/{$case->id}");

        $response->assertOk();
        $response->assertSee('Case source text');
        $response->assertDontSee('raw_message_metadata');
        $response->assertDontSee('telegram_file_id');
    }

    public function test_case_resolve_and_reject_send_customer_updates_without_touching_chat_state(): void
    {
        $this->setTelegramToken();
        $this->fakeTelegram();

        [$customer, $conversation, $textMessage, $imageMessage] = $this->seedConversationWithMessages();

        $resolveCase = SupportCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'message_id' => $textMessage->id,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'movie_request',
            'title' => 'Resolve case',
            'description' => 'Resolve me',
            'status' => 'open',
            'priority' => 'normal',
            'source_text' => $textMessage->text,
            'source_metadata' => ['message_id' => $textMessage->id],
        ]);

        $rejectCase = SupportCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'message_id' => $imageMessage->id,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'complaint',
            'title' => 'Reject case',
            'description' => 'Reject me',
            'status' => 'open',
            'priority' => 'normal',
            'source_text' => $imageMessage->metadata['caption'],
            'source_metadata' => ['message_id' => $imageMessage->id],
        ]);

        $this->post("/cases/{$resolveCase->id}/resolve", [
            'resolution_message' => 'တောင်းထားတဲ့အကြောင်းအရာကို ဆောင်ရွက်ပြီးပါပြီ။ ကျေးဇူးတင်ပါတယ်။',
        ])->assertRedirect();

        $resolveCase->refresh();
        $conversation->refresh();
        $this->assertSame('resolved', $resolveCase->status);
        $this->assertNotNull($resolveCase->resolved_at);
        $this->assertSame('Needs Reply', $conversation->status);
        $this->assertFalse((bool) $conversation->bot_paused);
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('sender_type', 'admin')->count());

        $this->post("/cases/{$rejectCase->id}/reject", [
            'rejection_message' => 'တောင်းထားတဲ့အကြောင်းအရာကို လက်ရှိ မရနိုင်သေးပါ။ အဆင်မပြေမှုအတွက် တောင်းပန်ပါတယ်။',
        ])->assertRedirect();

        $rejectCase->refresh();
        $conversation->refresh();
        $this->assertSame('rejected', $rejectCase->status);
        $this->assertSame('Needs Reply', $conversation->status);
        $this->assertFalse((bool) $conversation->bot_paused);
        $this->assertSame(2, Message::where('conversation_id', $conversation->id)->where('sender_type', 'admin')->count());
    }

    public function test_conversation_timeline_still_displays_messages(): void
    {
        [$customer, $conversation, $textMessage] = $this->seedConversationWithMessages();

        $botMessage = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'message_type' => 'text',
            'text' => 'Bot timeline reply',
            'raw_payload' => null,
            'metadata' => ['source' => 'faq'],
        ]);

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}");

        $response->assertOk();
        $response->assertSee('ဒီကားတင်ပေးပါ');
        $response->assertSee('Bot timeline reply');
        $response->assertSeeInOrder(['ဒီကားတင်ပေးပါ', 'Bot timeline reply']);
        $orderedIds = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertCount(4, $orderedIds);
        $this->assertSame($textMessage->id, $orderedIds[0]);
        $this->assertSame($botMessage->id, $orderedIds[3]);
    }

    protected function seedConversationWithMessages(): array
    {
        $customer = Customer::create([
            'platform' => 'telegram',
            'platform_user_id' => 'case-user-' . str()->random(8),
            'display_name' => 'Case User',
            'username' => 'caseuser',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'Needs Reply',
            'bot_paused' => false,
            'last_message_at' => now(),
        ]);

        $textMessage = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'text' => 'ဒီကားတင်ပေးပါ',
            'raw_payload' => ['message_id' => random_int(1000, 9999)],
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
            'raw_payload' => ['message_id' => random_int(1000, 9999)],
            'metadata' => [
                'telegram_file_id' => 'file_image_abc',
                'telegram_file_unique_id' => 'unique_image_abc',
                'caption' => 'caption for image',
                'width' => 1280,
                'height' => 960,
            ],
        ]);

        $fileMessage = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'file',
            'text' => null,
            'raw_payload' => ['message_id' => random_int(1000, 9999)],
            'metadata' => [
                'telegram_file_id' => 'file_doc_abc',
                'telegram_file_unique_id' => 'unique_doc_abc',
                'caption' => 'caption for file',
            ],
        ]);

        return [$customer, $conversation, $textMessage, $imageMessage, $fileMessage];
    }

    protected function setTelegramToken(): void
    {
        putenv('TELEGRAM_BOT_TOKEN=test-token');
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'test-token';
        $_SERVER['TELEGRAM_BOT_TOKEN'] = 'test-token';
    }

    protected function fakeTelegram(): void
    {
        app()->instance(TelegramBotService::class, new class extends TelegramBotService {
            public function sendMessage(string $chatId, string $text): bool
            {
                return true;
            }
        });
    }

    public function test_create_case_with_notification_replaces_case_id_and_saves_message(): void
    {
        [$customer, $conversation, $textMessage] = $this->seedConversationWithMessages();

        $this->setTelegramToken();
        $this->fakeTelegram();

        $originalStatus = $conversation->fresh()->status;
        $originalBotPaused = $conversation->fresh()->bot_paused;

        $notification = 'Case {case_id} created. We will get back to you.';

        $this->post(
            "/customers/{$customer->platform}/{$customer->platform_user_id}/cases",
            [
                'message_id' => $textMessage->id,
                'category' => 'movie_request',
                'title' => 'Notification test',
                'priority' => 'normal',
                'status' => 'open',
                'notification' => $notification,
            ]
        )->assertRedirect();

        $case = SupportCase::latest()->first();
        $code = $case->displayCode();

        // Notification message saved in timeline
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'admin',
        ]);

        $notifyMsg = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('sender_type', 'admin')
            ->latest()
            ->first();

        $this->assertStringContainsString($code, $notifyMsg->text);
        $this->assertStringNotContainsString('{case_id}', $notifyMsg->text);
        $this->assertEquals('dashboard', $notifyMsg->metadata['source'] ?? null);
        $this->assertEquals('case_created_notification', $notifyMsg->metadata['event'] ?? null);

        // Conversation state unchanged
        $this->assertEquals($originalStatus, $conversation->fresh()->status);
        $this->assertEquals($originalBotPaused, $conversation->fresh()->bot_paused);
    }

    public function test_create_case_without_notification_does_not_send(): void
    {
        [$customer, $conversation, $textMessage] = $this->seedConversationWithMessages();

        $this->setTelegramToken();
        $this->fakeTelegram();

        $adminMsgCountBefore = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('sender_type', 'admin')
            ->count();

        $this->post(
            "/customers/{$customer->platform}/{$customer->platform_user_id}/cases",
            [
                'message_id' => $textMessage->id,
                'category' => 'movie_request',
                'title' => 'No notification',
                'priority' => 'normal',
                'status' => 'open',
                'notification' => '',
            ]
        )->assertRedirect();

        $adminMsgCountAfter = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('sender_type', 'admin')
            ->count();

        $this->assertEquals($adminMsgCountBefore, $adminMsgCountAfter,
            'No admin outbound message should be created when notification is blank');
    }

    public function test_cases_index_renders_with_filters(): void
    {
        [$customer, $conversation, $textMessage] = $this->seedConversationWithMessages();

        SupportCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'message_id' => $textMessage->id,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'movie_request',
            'title' => 'Filter test open',
            'status' => 'open',
            'priority' => 'high',
            'source_text' => 'test',
            'source_metadata' => ['source' => 'test'],
        ]);

        SupportCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'message_id' => $textMessage->id,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'complaint',
            'title' => 'Filter test resolved',
            'status' => 'resolved',
            'priority' => 'normal',
            'source_text' => 'test',
            'source_metadata' => ['source' => 'test'],
        ]);

        $this->get('/cases')->assertOk()->assertSee('Filter test open');
        $this->get('/cases?status=open')->assertOk()->assertSee('Filter test open')->assertDontSee('Filter test resolved');
        $this->get('/cases?status=resolved')->assertOk()->assertSee('Filter test resolved')->assertDontSee('Filter test open');
        $this->get('/cases?status=rejected')->assertOk()->assertDontSee('Filter test open');
        $this->get('/cases?order=oldest')->assertOk();
        $this->get('/cases?order=newest')->assertOk();
        $this->get('/cases?status=invalid')->assertOk();
        $this->get('/cases?q=Filter+test+open')->assertOk()->assertSee('Filter test open');

        $response = $this->get('/cases');
        $response->assertDontSee('source_metadata');
    }
}
