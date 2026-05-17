<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public function sendMessage(string $chatId, string $text): bool
    {
        $token = env('TELEGRAM_BOT_TOKEN');

        if (empty($token)) {
            Log::warning('TELEGRAM_BOT_TOKEN not set. Cannot send Telegram message.');
            return false;
        }

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        return $response->successful();
    }
}
