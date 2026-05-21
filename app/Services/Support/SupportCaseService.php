<?php

namespace App\Services\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\SupportCase;
use App\Services\Telegram\TelegramBotService;
use InvalidArgumentException;
use Illuminate\Support\Collection;

class SupportCaseService
{
    public function createFromMessage(Message $message, array $data): SupportCase
    {
        if ($message->direction !== 'inbound' || $message->sender_type !== 'customer') {
            throw new InvalidArgumentException('Support cases can only be created from inbound customer messages.');
        }

        $message->loadMissing('customer', 'conversation');

        $sourceText = $message->text
            ?: ($message->metadata['caption'] ?? null);

        $sourceMetadata = array_filter([
            'message_id' => $message->id,
            'message_type' => $message->message_type,
            'direction' => $message->direction,
            'sender_type' => $message->sender_type,
            'raw_message_metadata' => $message->metadata,
        ], static fn ($value) => $value !== null && $value !== []);

        $case = SupportCase::create([
            'customer_id' => $message->customer_id,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'platform' => $message->platform,
            'platform_user_id' => $message->customer?->platform_user_id,
            'category' => $data['category'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'open',
            'priority' => $data['priority'] ?? 'normal',
            'source_text' => $sourceText,
            'source_metadata' => $sourceMetadata,
            'admin_note' => $data['admin_note'] ?? null,
            'resolved_at' => ($data['status'] ?? 'open') === 'resolved' ? now() : null,
        ]);

        return $case->fresh(['customer', 'conversation', 'message']);
    }

    public function recentSourceMessages(Conversation $conversation): Collection
    {
        return $conversation->messages()
            ->where('direction', 'inbound')
            ->where('sender_type', 'customer')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function createFromConversationSelection(Conversation $conversation, int $messageId, array $data): SupportCase
    {
        $message = $conversation->messages()
            ->whereKey($messageId)
            ->where('direction', 'inbound')
            ->where('sender_type', 'customer')
            ->firstOrFail();

        return $this->createFromMessage($message, $data);
    }

    public function deliverCustomerUpdate(
        SupportCase $case,
        string $status,
        string $text,
        ConversationService $conversationService,
        TelegramBotService $botService,
    ): bool {
        $conversation = $case->conversation()->with('customer')->first();

        if (! $conversation || ! $case->platform_user_id) {
            return false;
        }

        $sent = $botService->sendMessage($case->platform_user_id, $text);

        if (! $sent) {
            return false;
        }

        $conversationService->saveAdminOutboundMessage(
            $conversation,
            $case->platform,
            $text,
            'text',
            [
                'source' => 'support_case',
                'event' => "case_{$status}",
                'support_case_id' => $case->id,
                'support_case_code' => $case->displayCode(),
            ],
        );

        $case->status = $status;
        $case->resolved_at = in_array($status, ['resolved', 'rejected'], true) ? now() : null;
        $case->save();

        return true;
    }
}
