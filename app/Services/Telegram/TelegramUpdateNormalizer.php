<?php

namespace App\Services\Telegram;

class TelegramUpdateNormalizer
{
    public function normalize(array $update): array
    {
        $message = $update['message'] ?? [];
        $from = $message['from'] ?? [];

        return [
            'platform' => 'telegram',
            'platform_user_id' => (string) ($from['id'] ?? ''),
            'display_name' => trim(
                ($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')
            ) ?: null,
            'username' => $from['username'] ?? null,
            'message_type' => isset($message['text']) ? 'text' : 'unknown',
            'text' => $message['text'] ?? null,
            'file_id' => null,
            'raw_payload' => $update,
            'metadata' => null,
        ];
    }
}
