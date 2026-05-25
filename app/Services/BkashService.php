<?php

namespace App\Services;

use App\Exceptions\BkashException;
use App\Models\BkashToken;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BkashService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $username,
        private readonly string $password,
        private readonly bool $sandboxMode,
    ) {}

    /**
     * Return a valid id_token, refreshing or re-granting as needed.
     * Uses a DB-backed lock so concurrent requests don't double-grant.
     */
    public function getValidToken(): string
    {
        $token = BkashToken::latest()->first();

        if ($token && $token->id_expiry > now()->timestamp) {
            return $token->id_token;
        }

        $lock = Cache::lock('bkash_token_refresh', 15);

        return $lock->block(10, function (): string {
            $fresh = BkashToken::latest()->first();

            if ($fresh && $fresh->id_expiry > now()->timestamp) {
                return $fresh->id_token;
            }

            if ($fresh && $fresh->refresh_expiry > now()->timestamp) {
                return $this->doRefreshToken($fresh);
            }

            return $this->doGrantToken();
        });
    }

    /**
     * POST /tokenized/checkout/token/grant
     * Obtains a brand-new id_token and refresh_token using merchant credentials.
     */
    private function doGrantToken(): string
    {
        Log::info('[bKash] Granting new token');

        $response = $this->authHttp()
            ->post("{$this->baseUrl}/tokenized/checkout/token/grant", [
                'app_key' => $this->appKey,
                'app_secret' => $this->appSecret,
            ]);

        $data = $response->json();

        if (! $response->successful() || ($data['statusCode'] ?? '') !== '0000') {
            throw new BkashException(
                'Failed to grant token: '.($data['statusMessage'] ?? 'Unknown error'),
                $data ?? [],
            );
        }

        $this->persistToken($data);

        return $data['id_token'];
    }

    /**
     * POST /tokenized/checkout/token/refresh
     * Extends access using the existing refresh_token.
     * Falls back to doGrantToken() if refresh itself fails.
     */
    private function doRefreshToken(BkashToken $existing): string
    {
        Log::info('[bKash] Refreshing token');

        $response = $this->authHttp()
            ->post("{$this->baseUrl}/tokenized/checkout/token/refresh", [
                'app_key' => $this->appKey,
                'app_secret' => $this->appSecret,
                'refresh_token' => $existing->refresh_token,
            ]);

        $data = $response->json();

        if (! $response->successful() || ($data['statusCode'] ?? '') !== '0000') {
            Log::warning('[bKash] Token refresh failed, falling back to grant', $data ?? []);

            return $this->doGrantToken();
        }

        $this->persistToken($data, $existing);

        return $data['id_token'];
    }

    private function persistToken(array $data, ?BkashToken $existing = null): void
    {
        $payload = [
            'sandbox_mode' => $this->sandboxMode,
            'id_token' => $data['id_token'],
            'id_expiry' => now()->addSeconds((int) ($data['expires_in'] ?? 3600) - 60)->timestamp,
            'refresh_token' => $data['refresh_token'],
            // bKash refresh_tokens are valid for 28 days; subtract 1 hour as a safety buffer.
            'refresh_expiry' => now()->addDays(28)->subHour()->timestamp,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            // Full grant (first use or post-28-day expiry): purge all stale rows so the
            // table never accumulates dead tokens, then insert the fresh one.
            BkashToken::query()->delete();
            BkashToken::create($payload);
        }
    }

    /**
     * POST /tokenized/checkout/create
     * Creates a new payment and returns the bkashURL to redirect the customer to.
     */
    public function createPayment(
        string $amount,
        string $invoiceNumber,
        string $callbackUrl,
        string $payerReference,
    ): array {
        Log::info('[bKash] Creating payment', [
            'invoice' => $invoiceNumber,
            'amount' => $amount,
            'payer' => $payerReference,
        ]);

        $response = $this->paymentHttp()
            ->post("{$this->baseUrl}/tokenized/checkout/create", [
                'mode' => '0011',
                'payerReference' => $payerReference,
                'callbackURL' => $callbackUrl,
                'amount' => $amount,
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $invoiceNumber,
            ]);

        $data = $response->json();

        if (! $response->successful() || ($data['statusCode'] ?? '') !== '0000') {
            throw new BkashException(
                'Failed to create payment: '.($data['statusMessage'] ?? 'Unknown error'),
                $data ?? [],
            );
        }

        return $data;
    }

    /**
     * POST /tokenized/checkout/execute
     * Finalises a payment after the customer completes authentication on bKash.
     * Only call this from the callback handler.
     */
    public function executePayment(string $paymentId): array
    {
        Log::info('[bKash] Executing payment', ['paymentID' => $paymentId]);

        $response = $this->paymentHttp()
            ->post("{$this->baseUrl}/tokenized/checkout/execute", [
                'paymentID' => $paymentId,
            ]);

        $data = $response->json();

        if (! $response->successful() || ($data['statusCode'] ?? '') !== '0000') {
            throw new BkashException(
                'Failed to execute payment: '.($data['statusMessage'] ?? 'Unknown error'),
                $data ?? [],
            );
        }

        return $data;
    }

    /**
     * GET /tokenized/checkout/payment/status?paymentID={paymentID}
     * Fallback — only call this when executePayment() times out or throws.
     * Check transactionStatus === "Completed" before trusting it.
     */
    public function queryPayment(string $paymentId): array
    {
        Log::info('[bKash] Querying payment', ['paymentID' => $paymentId]);

        $response = $this->paymentHttp()
            ->get("{$this->baseUrl}/tokenized/checkout/payment/status", [
                'paymentID' => $paymentId,
            ]);

        return $response->json() ?? [];
    }

    // ─── HTTP Helpers ─────────────────────────────────────────────────────────

    /** HTTP client pre-loaded with merchant credential headers (for token endpoints). */
    private function authHttp(): PendingRequest
    {
        return $this->baseHttp()->withHeaders([
            'username' => $this->username,
            'password' => $this->password,
        ]);
    }

    /** HTTP client pre-loaded with a valid bearer token (for payment endpoints). */
    private function paymentHttp(): PendingRequest
    {
        return $this->baseHttp()->withHeaders([
            'Authorization' => $this->getValidToken(),
            'X-APP-Key' => $this->appKey,
        ]);
    }

    private function baseHttp(): PendingRequest
    {
        return Http::timeout(30)
            ->retry(2, 500, throw: false)
            ->acceptJson()
            ->contentType('application/json');
    }
}
