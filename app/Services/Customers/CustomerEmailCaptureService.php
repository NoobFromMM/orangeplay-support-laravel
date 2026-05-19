<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerEmail;
use InvalidArgumentException;

class CustomerEmailCaptureService
{
    public function extractEmail(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        $trimmed = trim($text);

        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            return $trimmed;
        }

        return null;
    }

    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function capture(
        Customer $customer,
        string $email,
        string $source = 'telegram_text',
        array $metadata = [],
    ): CustomerEmail {
        $valid = $this->extractEmail($email);

        if (! $valid) {
            throw new InvalidArgumentException("Invalid email: {$email}");
        }

        $normalized = $this->normalizeEmail($valid);
        $now = now();

        $customerEmail = CustomerEmail::where('customer_id', $customer->id)
            ->where('normalized_email', $normalized)
            ->first();

        if ($customerEmail) {
            $customerEmail->update([
                'email' => $valid,
                'source' => $source,
                'last_seen_at' => $now,
                'metadata' => $metadata ? array_merge($customerEmail->metadata ?? [], $metadata) : $customerEmail->metadata,
            ]);
        } else {
            $customerEmail = CustomerEmail::create([
                'customer_id' => $customer->id,
                'email' => $valid,
                'normalized_email' => $normalized,
                'source' => $source,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'metadata' => $metadata,
            ]);
        }

        return $customerEmail;
    }
}
