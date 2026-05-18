<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentCheckClient
{
    protected ?string $workerUrl;
    protected ?string $workerSecret;

    public function __construct(?string $workerUrl = null, ?string $workerSecret = null)
    {
        $this->workerUrl = $workerUrl ?? env('PAYMENT_CHECK_WORKER_URL');
        $this->workerSecret = $workerSecret ?? env('PAYMENT_CHECK_WORKER_SECRET');
    }

    public function checkImageBytes(string $bytes, array $metadata = []): array
    {
        if (empty($this->workerUrl)) {
            return [
                'ok' => false,
                'is_payment' => false,
                'error' => 'PAYMENT_CHECK_WORKER_URL is not set',
            ];
        }

        $response = Http::timeout(30)
            ->withHeaders($this->buildHeaders())
            ->attach('file', $bytes, 'screenshot.jpg')
            ->post($this->workerUrl, $metadata);

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
        if (empty($this->workerSecret)) {
            return [];
        }

        return ['Authorization' => "Bearer {$this->workerSecret}"];
    }
}
