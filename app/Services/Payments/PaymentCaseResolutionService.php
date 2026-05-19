<?php

namespace App\Services\Payments;

use App\Models\Message;
use App\Models\PaymentCase;
use InvalidArgumentException;

class PaymentCaseResolutionService
{
    public function approve(PaymentCase $case, array $context = []): PaymentCase
    {
        return $this->resolve($case, 'approved', $context);
    }

    public function reject(PaymentCase $case, array $context = []): PaymentCase
    {
        return $this->resolve($case, 'rejected', $context);
    }

    protected function resolve(PaymentCase $case, string $newStatus, array $context): PaymentCase
    {
        $currentStatus = $case->status;

        if (! in_array($currentStatus, ['pending_review'], true)) {
            throw new InvalidArgumentException(
                "Payment case id={$case->id} has status={$currentStatus}. Only pending_review cases can be resolved."
            );
        }

        $oldStatus = $currentStatus;

        $case->update([
            'status' => $newStatus,
            'reviewed_at' => now(),
            'reviewed_by' => $context['reviewer_id'] ?? $case->reviewed_by,
        ]);

        $platform = $case->conversation?->messages()->latest()->first()?->platform ?? 'telegram';

        Message::create([
            'conversation_id' => $case->conversation_id,
            'customer_id' => $case->customer_id,
            'platform' => $platform,
            'direction' => 'system',
            'sender_type' => 'system',
            'message_type' => 'payment_status_update',
            'text' => $newStatus === 'approved' ? 'Payment approved' : 'Payment rejected',
            'metadata' => [
                'payment_case_id' => $case->id,
                'action' => $newStatus,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reviewer_id' => $context['reviewer_id'] ?? null,
                'reviewer_name' => $context['reviewer_name'] ?? null,
                'note' => $context['note'] ?? null,
            ],
        ]);

        return $case;
    }
}
