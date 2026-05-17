<?php

namespace App\Services\Support;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;

class ConversationService
{
    public function findOrCreateCustomer(string $platform, string $platformUserId, array $extra = []): Customer
    {
        $customer = Customer::where('platform', $platform)
            ->where('platform_user_id', $platformUserId)
            ->first();

        if (! $customer) {
            $customer = Customer::create(array_merge([
                'platform' => $platform,
                'platform_user_id' => $platformUserId,
            ], $extra));
        } else {
            $changed = false;
            foreach ($extra as $key => $value) {
                if ($value !== null && $customer->{$key} !== $value) {
                    $customer->{$key} = $value;
                    $changed = true;
                }
            }
            if ($changed) {
                $customer->save();
            }
        }

        return $customer;
    }

    public function findOrCreateConversation(Customer $customer): Conversation
    {
        $conversation = Conversation::where('customer_id', $customer->id)
            ->whereIn('status', ['new', 'open', 'resolved', 'in_chat', 'Needs Reply'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'status' => 'new',
            ]);
        }

        return $conversation;
    }

    public function saveInboundMessage(
        Conversation $conversation,
        array $normalized
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'platform' => $normalized['platform'],
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => $normalized['message_type'],
            'text' => $normalized['text'],
            'raw_payload' => $normalized['raw_payload'],
            'metadata' => $normalized['metadata'],
        ]);

        $this->touchConversation($conversation);

        return $message;
    }

    public function saveOutboundMessage(
        Conversation $conversation,
        string $platform,
        string $text,
        string $messageType = 'text'
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'platform' => $platform,
            'direction' => 'outbound',
            'sender_type' => 'bot',
            'message_type' => $messageType,
            'text' => $text,
        ]);

        $this->touchConversation($conversation);

        return $message;
    }

    public function setStatus(Conversation $conversation, string $status): void
    {
        $conversation->status = $status;
        $conversation->save();
    }

    public function saveAdminOutboundMessage(
        Conversation $conversation,
        string $platform,
        string $text,
        string $messageType = 'text',
        ?array $metadata = null,
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'platform' => $platform,
            'direction' => 'outbound',
            'sender_type' => 'admin',
            'message_type' => $messageType,
            'text' => $text,
            'metadata' => $metadata,
        ]);

        $this->touchConversation($conversation);

        return $message;
    }

    protected function touchConversation(Conversation $conversation): void
    {
        $conversation->last_message_at = now();
        $conversation->save();
    }
}
