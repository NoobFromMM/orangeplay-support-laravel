<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentCheckClient
{
    public function checkImageBytes(string $bytes, array $metadata = []): array
    {
        $url = env('PAYMENT_CHECK_WORKER_URL');

        if (empty($url)) {
            return [
                'ok' => false,
                'is_payment' => false,
                'error' => 'PAYMENT_CHECK_WORKER_URL is not set',
            ];
        }

        $response = Http::timeout(30)
            ->withHeaders($this->buildHeaders())
            ->attach('file', $bytes, 'screenshot.jpg')
            ->post($url, $metadata);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'is_payment' => false,
                'error' => "Worker returned HTTP {$response->status()}",
                'raw' => $response->body(),
            ];
        }

        $body = $response->json() ?? [];

        return [
            'ok' => $body['ok'] ?? false,
            'is_payment' => $body['is_payment'] ?? false,
            'provider' => $body['provider'] ?? null,
            'transaction_id' => $body['transaction_id'] ?? null,
            'amount' => $body['amount'] ?? null,
            'confidence' => $body['confidence'] ?? null,
            'reason' => $body['reason'] ?? null,
            'error' => $body['error'] ?? null,
            'raw' => $body,
        ];
    }

    protected function buildHeaders(): array
    {
        $secret = env('PAYMENT_CHECK_WORKER_SECRET');

        if (empty($secret)) {
            return [];
        }

        return ['Authorization' => "Bearer {$secret}"];
    }
}
