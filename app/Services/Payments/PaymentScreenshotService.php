<?php

namespace App\Services\Payments;

use App\Models\Message;
use App\Models\PaymentCase;
use InvalidArgumentException;

class PaymentScreenshotService
{
    public function __construct(
        protected PaymentCaseService $paymentCaseService,
    ) {}

    public function processImageMessage(Message $imageMessage, array $workerResult): ?PaymentCase
    {
        if ($imageMessage->message_type !== 'image') {
            throw new InvalidArgumentException(
                "Expected message_type=image, got {$imageMessage->message_type}"
            );
        }

        if (empty($workerResult['is_payment'])) {
            return null;
        }

        $customer = $imageMessage->customer;
        $conversation = $imageMessage->conversation;

        $paymentCase = $this->paymentCaseService->createFromWorkerResult(
            $customer,
            $conversation,
            $imageMessage,
            $workerResult,
        );

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform' => $imageMessage->platform,
            'direction' => 'system',
            'sender_type' => 'system',
            'message_type' => 'payment_review_card',
            'text' => 'Payment screenshot detected',
            'metadata' => [
                'payment_case_id' => $paymentCase->id,
                'provider' => $workerResult['provider'] ?? null,
                'transaction_id' => $workerResult['transaction_id'] ?? null,
                'amount' => $workerResult['amount'] ?? null,
                'confidence' => $workerResult['confidence'] ?? null,
            ],
        ]);

        $conversation->last_message_at = now();
        $conversation->save();

        return $paymentCase;
    }
}
