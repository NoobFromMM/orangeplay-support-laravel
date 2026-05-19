<?php

namespace App\Services\Payments;

use App\Models\PaymentCase;

class DuplicatePaymentDetector
{
    public function findDuplicate(?string $provider, ?string $transactionId): ?PaymentCase
    {
        $transactionId = $this->normalizeTransactionId($transactionId);

        if (empty($transactionId)) {
            return null;
        }

        return PaymentCase::where('transaction_id', $transactionId)
            ->latest()
            ->first();
    }

    public function normalizeProvider(?string $provider): ?string
    {
        if ($provider === null || trim((string) $provider) === '') {
            return null;
        }

        $cleaned = mb_strtolower(trim((string) $provider));
        $cleaned = preg_replace('/[\s_\-]/u', '', $cleaned);

        return $cleaned ?: null;
    }

    public function normalizeTransactionId(?string $transactionId): ?string
    {
        if ($transactionId === null || trim((string) $transactionId) === '') {
            return null;
        }

        $cleaned = trim((string) $transactionId);

        $invalidValues = [
            'null', 'undefined', 'not explicitly visible', 'not visible',
            'not found', 'not readable', 'unclear', 'unknown', 'n/a', 'na', '-',
        ];

        if (in_array(mb_strtolower($cleaned), $invalidValues, true)) {
            return null;
        }

        if (mb_strlen($cleaned) < 4) {
            return null;
        }

        return $cleaned;
    }
}
