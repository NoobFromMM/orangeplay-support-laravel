<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentCheckClient
{
    protected const KNOWN_PROVIDERS = [
        'kpay', 'kbzpay', 'kbz', 'wave', 'wavepay',
        'ayapay', 'aya', 'cbpay', 'cb', 'uabpay', 'uab',
        'onepay', 'mpitesan', 'mpitesanpay',
    ];

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

        $explicitIsPayment = $content['is_payment']
            ?? $content['isPayment']
            ?? $content['is_receipt']
            ?? false;

        $provider = $this->normalizeProvider(
            $content['app']
            ?? $content['provider']
            ?? $content['payment_provider']
            ?? null
        );

        $transactionId = $this->cleanTransactionId(
            $content['transaction_id']
            ?? $content['txn_id']
            ?? $content['transactionId']
            ?? $content['payment_transaction_id']
            ?? null
        );

        $derivedIsPayment = $this->deriveIsPayment($explicitIsPayment, $provider, $transactionId);

        return [
            'ok' => $body['ok'] ?? true,
            'is_payment' => $derivedIsPayment,
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'amount' => $content['amount'] ?? null,
            'confidence' => $content['confidence'] ?? null,
            'reason' => $content['reason'] ?? null,
            'error' => $body['error'] ?? $content['error'] ?? null,
            'raw' => $body,
        ];
    }

    protected function deriveIsPayment($explicitIsPayment, ?string $provider, ?string $transactionId): bool
    {
        if ($explicitIsPayment && $explicitIsPayment !== 'false' && $explicitIsPayment !== '0') {
            return true;
        }

        if ($this->isValidTransactionId($transactionId)) {
            return true;
        }

        if ($this->isKnownProvider($provider)) {
            return true;
        }

        return false;
    }

    protected function normalizeProvider(?string $provider): ?string
    {
        if ($provider === null || trim((string) $provider) === '') {
            return null;
        }

        $cleaned = mb_strtolower(trim((string) $provider));
        $cleaned = preg_replace('/[\s_\-]/u', '', $cleaned);

        return $cleaned ?: null;
    }

    protected function isKnownProvider(?string $provider): bool
    {
        if ($provider === null) {
            return false;
        }

        return in_array($provider, self::KNOWN_PROVIDERS, true);
    }

    protected function cleanTransactionId($transactionId): ?string
    {
        if ($transactionId === null) {
            return null;
        }

        $cleaned = trim((string) $transactionId);

        if ($cleaned === '') {
            return null;
        }

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

    protected function isValidTransactionId(?string $transactionId): bool
    {
        return $transactionId !== null;
    }
}
