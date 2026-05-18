<?php

namespace App\Services\Payments;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\PaymentCase;
use InvalidArgumentException;

class PaymentCaseService
{
    public function createFromWorkerResult(
        Customer $customer,
        Conversation $conversation,
        ?Message $imageMessage,
        array $workerResult,
    ): PaymentCase {
        if (empty($workerResult['is_payment'])) {
            throw new InvalidArgumentException('Cannot create payment case: worker result is_payment is false.');
        }

        $status = 'pending_review';

        return PaymentCase::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'image_message_id' => $imageMessage?->id,
            'provider' => $workerResult['provider'] ?? null,
            'transaction_id' => $workerResult['transaction_id'] ?? null,
            'amount' => $workerResult['amount'] ?? null,
            'currency' => 'MMK',
            'status' => $status,
            'customer_email' => null,
            'worker_response' => $workerResult,
        ]);
    }
}
