<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentCheckClient
{
    protected ?string $workerUrl;
    protected ?string $agentToken;
    protected ?string $geminiKey;

    public function __construct(
        ?string $workerUrl = null,
        ?string $agentToken = null,
        ?string $geminiKey = null,
    ) {
        $this->workerUrl = $workerUrl ?? env('PAYMENT_CHECK_WORKER_URL');
        $this->agentToken = $agentToken ?? env('AGENT_TOKEN') ?: env('PAYMENT_CHECK_WORKER_SECRET');
        $this->geminiKey = $geminiKey ?? env('GEMINI_KEY') ?: env('PAYMENT_CHECK_GEMINI_KEY');
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

        if (empty($this->agentToken)) {
            return [
                'ok' => false,
                'is_payment' => false,
                'error' => 'AGENT_TOKEN is not set',
            ];
        }

        if (empty($this->geminiKey)) {
            return [
                'ok' => false,
                'is_payment' => false,
                'error' => 'GEMINI_KEY is not set',
            ];
        }

        $response = Http::timeout(30)
            ->attach('image', $bytes, 'screenshot.jpg')
            ->post($this->workerUrl, array_filter([
                'mode' => 'payment_check',
                'agent_token' => $this->agentToken,
                'gemini_key' => $this->geminiKey,
            ]));

        if (! $response->successful()) {
            return [
                'ok' => false,
                'is_payment' => false,
                'error' => "Worker returned HTTP {$response->status()}",
                'raw' => $response->body(),
            ];
        }

        $body = $response->json() ?? [];

        return $this->normalizeResponse($body);
    }

    protected function normalizeResponse(array $body): array
    {
        $content = $body['data']['content'] ?? $body;

        $isPayment = $content['is_payment'] ?? false;

        $provider = $content['app']
            ?? $content['provider']
            ?? $content['payment_provider']
            ?? null;

        $transactionId = $content['transaction_id']
            ?? $content['txn_id']
            ?? $content['transactionId']
            ?? $content['payment_transaction_id']
            ?? null;

        return [
            'ok' => $body['ok'] ?? true,
            'is_payment' => (bool) $isPayment,
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'amount' => $content['amount'] ?? null,
            'confidence' => $content['confidence'] ?? null,
            'reason' => $content['reason'] ?? null,
            'error' => $body['error'] ?? $content['error'] ?? null,
            'raw' => $body,
        ];
    }
}
