<?php

namespace App\Services\Support;

class GreetingMatcher
{
    public const GREETING_REPLY = "မင်္ဂလာပါရှင့် Orange Play Customer Service မှကြိုဆိုပါတယ်။ဘာများကူညီပေးရမလဲရှင့်";

    protected array $greetings = [
        'hi',
        'hello',
        'hey',
        'mingalarbar',
        'မင်္ဂလာပါ',
        'မင်လာပါ',
        'ဟိုင်း',
        'hello ပါ',
    ];

    public function isGreeting(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        $normalized = mb_strtolower(trim($text));

        foreach ($this->greetings as $greeting) {
            if ($normalized === mb_strtolower($greeting)) {
                return true;
            }
        }

        return false;
    }

    public function getReplyText(): string
    {
        return self::GREETING_REPLY;
    }
}
