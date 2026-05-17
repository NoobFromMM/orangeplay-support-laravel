<?php

namespace App\Services\Telegram;

class TelegramUpdateNormalizer
{
    public function normalize(array $update): array
    {
        $message = $update['message'] ?? [];
        $from = $message['from'] ?? [];

        $messageType = 'unknown';
        $text = null;
        $metadata = null;

        if (isset($message['text'])) {
            $messageType = 'text';
            $text = $message['text'];
        } elseif (isset($message['photo'])) {
            $messageType = 'image';
            $text = $message['caption'] ?? null;
            $metadata = $this->extractPhotoMetadata($message);
        }

        return [
            'platform' => 'telegram',
            'platform_user_id' => (string) ($from['id'] ?? ''),
            'display_name' => trim(
                ($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')
            ) ?: null,
            'username' => $from['username'] ?? null,
            'message_type' => $messageType,
            'text' => $text,
            'file_id' => null,
            'raw_payload' => $update,
            'metadata' => $metadata,
        ];
    }

    protected function extractPhotoMetadata(array $message): ?array
    {
        $photos = $message['photo'] ?? [];

        if (empty($photos)) {
            return null;
        }

        // Pick largest photo by file_size or last in array
        $largest = null;
        foreach ($photos as $photo) {
            if ($largest === null || ($photo['file_size'] ?? 0) > ($largest['file_size'] ?? 0)) {
                $largest = $photo;
            }
        }

        if ($largest === null) {
            return null;
        }

        return array_filter([
            'telegram_file_id' => $largest['file_id'] ?? null,
            'telegram_file_unique_id' => $largest['file_unique_id'] ?? null,
            'width' => $largest['width'] ?? null,
            'height' => $largest['height'] ?? null,
            'file_size' => $largest['file_size'] ?? null,
            'caption' => $message['caption'] ?? null,
            'photo_count' => count($photos),
        ], fn ($v) => $v !== null);
    }
}
