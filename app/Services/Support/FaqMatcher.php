<?php

namespace App\Services\Support;

use App\Models\FaqEntry;

class FaqMatcher
{
    protected const SHORT_PHRASE_MAX_LENGTH = 15;

    public function match(?string $text): ?FaqEntry
    {
        if ($text === null || $text === '') {
            return null;
        }

        $normalized = $this->normalize($text);

        $entries = FaqEntry::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($entries as $entry) {
            $keywords = $entry->keywords;
            if (! is_array($keywords)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (! is_string($keyword) || $keyword === '') {
                    continue;
                }

                $normalizedKeyword = $this->normalize($keyword);

                if ($normalizedKeyword === '') {
                    continue;
                }

                if (str_contains($normalized, $normalizedKeyword)) {
                    return $entry;
                }

                if (mb_strlen($normalized) <= self::SHORT_PHRASE_MAX_LENGTH
                    && str_contains($normalizedKeyword, $normalized)) {
                    return $entry;
                }
            }
        }

        return null;
    }

    protected function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[!?#.,;:]+$/u', '', $text);

        return $text;
    }
}
