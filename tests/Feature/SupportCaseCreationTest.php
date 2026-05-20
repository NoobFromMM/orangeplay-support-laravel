<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SupportCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportCaseCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_customer_message_shows_create_case_link(): void
    {
        [$customer, $message] = $this->seedTextMessage('Need help with movie');

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}");

        $response->assertOk();
        $response->assertSee('Create Case');
        $response->assertSee('Need help with movie');
        $response->assertSee('/messages/' . $message->id . '/cases/create', false);
    }

    public function test_admin_can_create_case_from_text_message(): void
    {
        [$customer, $message] = $this->seedTextMessage('ဒီကားတင်ပေးပါ');

        $this->get("/messages/{$message->id}/cases/create")->assertOk();

        $response = $this->post("/messages/{$message->id}/cases", [
            'category' => 'movie_request',
            'title' => 'Movie request from Telegram',
            'description' => 'Customer requested a title.',
            'priority' => 'high',
            'admin_note' => 'Handle soon',
            'status' => 'open',
        ]);

        $response->assertRedirect();

        $case = SupportCase::latest('id')->first();
        $this->assertNotNull($case);
        $this->assertSame($customer->id, $case->customer_id);
        $this->assertSame($message->id, $case->message_id);
        $this->assertSame('movie_request', $case->category);
        $this->assertSame('open', $case->status);
        $this->assertSame('high', $case->priority);
        $this->assertSame('ဒီကားတင်ပေးပါ', $case->source_text);
        $this->assertSame('telegram', $case->platform);

        $this->get('/cases')->assertOk()->assertSee('Movie request from Telegram');
        $this->get("/cases/{$case->id}")->assertOk()->assertSee('Open source conversation');
    }

    public function test_admin_can_create_case_from_image_message_with_metadata(): void
    {
        $customer = Customer::create([
            'platform' => 'telegram',
            'platform_user_id' => 'image-case-user',
            'display_name' => 'Image Case User',
            'username' => 'imagecase',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'Needs Reply',
            'last_message_at' => now(),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'image',
            'text' => null,
            'raw_payload' => ['message_id' => 22],
            'metadata' => [
                'telegram_file_id' => 'file_abc123',
                'telegram_file_unique_id' => 'unique_abc123',
                'width' => 1280,
                'height' => 960,
                'caption' => 'ဒီပုံထဲကကားရနိုင်မလား',
            ],
        ]);

        $response = $this->post("/messages/{$message->id}/cases", [
            'category' => 'movie_request',
            'title' => 'Image based movie request',
            'description' => 'Customer shared a screenshot.',
            'priority' => 'normal',
            'admin_note' => null,
            'status' => 'open',
        ]);

        $response->assertRedirect();

        $case = SupportCase::latest('id')->first();
        $this->assertNotNull($case);
        $this->assertSame('ဒီပုံထဲကကားရနိုင်မလား', $case->source_text);
        $this->assertSame('file_abc123', $case->source_metadata['raw_message_metadata']['telegram_file_id'] ?? null);
        $this->assertSame('unique_abc123', $case->source_metadata['raw_message_metadata']['telegram_file_unique_id'] ?? null);
    }

    public function test_invalid_case_fields_are_rejected(): void
    {
        [$customer, $message] = $this->seedTextMessage('Need validation');

        $response = $this->post("/messages/{$message->id}/cases", [
            'category' => 'not-a-category',
            'title' => 'Bad case',
            'description' => 'Bad case',
            'priority' => 'urgent',
            'admin_note' => 'Bad',
            'status' => 'closed',
        ]);

        $response->assertSessionHasErrors(['category', 'priority', 'status']);
        $this->assertSame(0, SupportCase::count());
    }

    public function test_case_index_displays_created_case(): void
    {
        [$customer, $message] = $this->seedTextMessage('Index me');

        SupportCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'complaint',
            'title' => 'Index Case',
            'description' => 'Shown in list',
            'status' => 'open',
            'priority' => 'normal',
            'source_text' => 'Index me',
            'source_metadata' => ['message_id' => $message->id],
        ]);

        $response = $this->get('/cases');

        $response->assertOk();
        $response->assertSee('Index Case');
        $response->assertSee('Complaint');
    }

    public function test_conversation_timeline_still_displays_messages(): void
    {
        [$customer, $message] = $this->seedTextMessage('Timeline still works');

        $botMessage = Message::create([
            'conversation_id' => $message->conversation_id,
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
        $response->assertSee('Timeline still works');
        $response->assertSee('Bot timeline reply');
        $response->assertSeeInOrder(['Timeline still works', 'Bot timeline reply']);
        $this->assertSame($message->id, Message::where('conversation_id', $message->conversation_id)->orderBy('created_at')->orderBy('id')->first()->id);
        $this->assertSame($botMessage->id, Message::where('conversation_id', $message->conversation_id)->orderBy('created_at')->orderBy('id')->skip(1)->first()->id);
    }

    protected function seedTextMessage(string $text): array
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
            'last_message_at' => now(),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'text' => $text,
            'raw_payload' => ['message_id' => random_int(1000, 9999)],
            'metadata' => null,
        ]);

        return [$customer, $message];
    }
}
