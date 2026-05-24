<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SupportCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardConversationTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_detail_shows_all_messages_in_chronological_order(): void
    {
        $customer = $this->createCustomer('timeline-user', 'Timeline User');
        $conversation = $this->createConversation($customer, 'in_chat');

        $first = $this->createMessage($conversation, $customer, 'inbound', 'customer', 'text', 'Customer hello', now()->subMinutes(15));
        $image = $this->createMessage($conversation, $customer, 'inbound', 'customer', 'image', null, now()->subMinutes(12), [
            'telegram_file_id' => 'file_001',
            'telegram_file_unique_id' => 'unique_001',
            'width' => 1280,
            'height' => 960,
        ]);
        $bot = $this->createMessage($conversation, $customer, 'outbound', 'bot', 'text', 'Bot reply', now()->subMinutes(9), ['source' => 'faq']);
        $admin = $this->createMessage($conversation, $customer, 'outbound', 'admin', 'text', 'Admin reply', now()->subMinutes(6), ['source' => 'dashboard']);
        $system = $this->createMessage($conversation, $customer, 'outbound', 'system', 'system', 'System note', now()->subMinutes(3), ['event' => 'system_note']);

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}");

        $response->assertOk();
        $response->assertSeeInOrder([
            'Customer hello',
            '/telegram/file/file_001',
            'Bot reply',
            'Admin reply',
            'System note',
        ]);

        $orderedIds = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$first->id, $image->id, $bot->id, $admin->id, $system->id], $orderedIds);
    }

    public function test_active_cases_summary_shows_only_open_cases(): void
    {
        $customer = $this->createCustomer('active-cases-user', 'Active Cases User');
        $conversation = $this->createConversation($customer, 'Needs Reply');

        $openCase = $this->createSupportCase($customer, $conversation, [
            'message_id' => null,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'movie_request',
            'title' => 'Open case',
            'description' => 'open',
            'status' => 'open',
            'priority' => 'normal',
            'source_text' => 'Open source',
            'source_metadata' => ['source' => 'test'],
        ], now()->subMinutes(12), null);

        $inProgressCase = $this->createSupportCase($customer, $conversation, [
            'message_id' => null,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'complaint',
            'title' => 'In progress case',
            'description' => 'working',
            'status' => 'in_progress',
            'priority' => 'high',
            'source_text' => 'In progress source',
            'source_metadata' => ['source' => 'test'],
        ], now()->subMinutes(10), null);

        $resolvedCase = $this->createSupportCase($customer, $conversation, [
            'message_id' => null,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'content_request',
            'title' => 'Resolved case',
            'description' => 'done',
            'status' => 'resolved',
            'priority' => 'low',
            'source_text' => 'Resolved source',
            'source_metadata' => ['source' => 'test'],
        ], now()->subMinutes(8), now()->subMinutes(2));

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}");

        $response->assertOk();
        $content = $response->getContent();

        $summaryStart = strpos($content, 'Active Cases');
        $timelineStart = strpos($content, '>Timeline</h2>');

        $this->assertNotFalse($summaryStart);
        $this->assertNotFalse($timelineStart);

        $summary = substr($content, $summaryStart, $timelineStart - $summaryStart);

        $this->assertStringContainsString('Open case', $summary);
        $this->assertStringNotContainsString('In progress case', $summary);
        $this->assertStringNotContainsString('Resolved case', $summary);
        $this->assertStringNotContainsString('Rejected case', $summary);
        $this->assertStringContainsString('View Case', $content);
    }

    public function test_conversation_timeline_includes_case_cards_in_ascending_order(): void
    {
        $customer = $this->createCustomer('case-asc-user', 'Case Asc User');
        $conversation = $this->createConversation($customer, 'Needs Reply');

        $beforeMessage = $this->createMessage($conversation, $customer, 'inbound', 'customer', 'text', 'Message before case', now()->subMinutes(15));
        $case = $this->createSupportCase($customer, $conversation, [
            'message_id' => $beforeMessage->id,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'movie_request',
            'title' => 'Chronological case',
            'description' => 'case body',
            'status' => 'resolved',
            'priority' => 'normal',
            'source_text' => 'Source body',
            'source_metadata' => ['source' => 'test'],
        ], now()->subMinutes(10), now()->subMinutes(4));

        $afterMessage = $this->createMessage($conversation, $customer, 'outbound', 'bot', 'text', 'Message after case', now()->subMinutes(7), ['source' => 'faq']);

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}?order=asc");

        $response->assertOk();
        $response->assertSee('Case Created');
        $response->assertSee('Case Resolved');
        $response->assertSee('Chronological case');

        $this->assertContentOrder($response->getContent(), [
            'Message before case',
            'Case Created',
            'Message after case',
            'Case Resolved',
        ]);
    }

    public function test_conversation_timeline_includes_case_cards_in_descending_order(): void
    {
        $customer = $this->createCustomer('case-desc-user', 'Case Desc User');
        $conversation = $this->createConversation($customer, 'Needs Reply');

        $beforeMessage = $this->createMessage($conversation, $customer, 'inbound', 'customer', 'text', 'Message before case', now()->subMinutes(15));
        $case = $this->createSupportCase($customer, $conversation, [
            'message_id' => $beforeMessage->id,
            'platform' => 'telegram',
            'platform_user_id' => $customer->platform_user_id,
            'category' => 'complaint',
            'title' => 'Reverse chronological case',
            'description' => 'case body',
            'status' => 'rejected',
            'priority' => 'high',
            'source_text' => 'Source body',
            'source_metadata' => ['source' => 'test'],
        ], now()->subMinutes(10), now()->subMinutes(4));

        $afterMessage = $this->createMessage($conversation, $customer, 'outbound', 'admin', 'text', 'Message after case', now()->subMinutes(7), ['source' => 'dashboard']);

        $response = $this->get("/customers/telegram/{$customer->platform_user_id}?order=desc");

        $response->assertOk();
        $response->assertSee('Case Created');
        $response->assertSee('Case Rejected');
        $response->assertSee('Reverse chronological case');

        $this->assertContentOrder($response->getContent(), [
            'Case Rejected',
            'Message after case',
            'Case Created',
            'Message before case',
        ]);
    }

    protected function createCustomer(string $platformUserId, string $displayName): Customer
    {
        return Customer::create([
            'platform' => 'telegram',
            'platform_user_id' => $platformUserId,
            'display_name' => $displayName,
            'username' => strtolower(str_replace(' ', '', $displayName)),
        ]);
    }

    protected function createConversation(Customer $customer, string $status): Conversation
    {
        return Conversation::create([
            'customer_id' => $customer->id,
            'status' => $status,
            'bot_paused' => false,
            'last_message_at' => now(),
        ]);
    }

    protected function createMessage(
        Conversation $conversation,
        Customer $customer,
        string $direction,
        string $senderType,
        string $messageType,
        ?string $text,
        Carbon $timestamp,
        ?array $metadata = null,
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => 'telegram',
            'direction' => $direction,
            'sender_type' => $senderType,
            'message_type' => $messageType,
            'text' => $text,
            'raw_payload' => ['message_id' => random_int(1000, 9999)],
            'metadata' => $metadata,
        ]);

        DB::table('messages')->where('id', $message->id)->update([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $message->refresh();
    }

    protected function createSupportCase(
        Customer $customer,
        Conversation $conversation,
        array $attributes,
        Carbon $createdAt,
        ?Carbon $resolvedAt,
    ): SupportCase {
        $case = SupportCase::create($attributes + [
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
        ]);

        DB::table('support_cases')->where('id', $case->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $resolvedAt ?? $createdAt,
            'resolved_at' => $resolvedAt,
        ]);

        return $case->refresh();
    }

    protected function assertContentOrder(string $content, array $needles): void
    {
        $cursor = -1;

        foreach ($needles as $needle) {
            $position = strpos($content, $needle, $cursor + 1);

            $this->assertNotFalse($position, "Expected to find {$needle} in content.");
            $this->assertGreaterThan($cursor, $position, "Expected {$needle} to appear after the previous item.");
            $cursor = $position;
        }
    }
}
