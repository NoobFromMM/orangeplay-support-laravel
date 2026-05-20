<?php

namespace App\Services\Support;

use App\Models\Message;
use App\Models\SupportCase;
use InvalidArgumentException;

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
}
