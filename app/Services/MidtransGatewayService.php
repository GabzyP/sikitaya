<?php

namespace App\Services;

use App\Contracts\MidtransGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;
use Midtrans\Transaction as MidtransTransaction;
use RuntimeException;
use Throwable;

class MidtransGatewayService implements MidtransGateway
{
    public function isConfigured(): bool
    {
        return $this->configurationError() === null;
    }

    public function configurationError(): ?string
    {
        if (! config('midtrans.enabled')) {
            return 'Integrasi Midtrans dinonaktifkan melalui MIDTRANS_ENABLED=false.';
        }

        if (config('midtrans.environment') === 'production' && ! config('midtrans.production_enabled')) {
            return 'Mode production diminta, tetapi MIDTRANS_PRODUCTION_ENABLED belum diaktifkan secara eksplisit.';
        }

        if (blank(config('midtrans.server_key')) || blank(config('midtrans.client_key'))) {
            return 'MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY belum diisi.';
        }

        return null;
    }

    public function isProduction(): bool
    {
        return config('midtrans.environment') === 'production'
            && (bool) config('midtrans.production_enabled');
    }

    public function splitEnabled(): bool
    {
        return $this->isConfigured() && (bool) config('midtrans.split.enabled');
    }

    public function disbursementEnabled(): bool
    {
        return $this->isConfigured()
            && (bool) config('midtrans.disbursement.enabled')
            && filled(config('midtrans.disbursement.creator_key'));
    }

    public function createSnapTransaction(array $payload): array
    {
        $this->ensureConfigured();
        $this->configurePackage();

        try {
            return $this->toArray(Snap::createTransaction($payload));
        } catch (Throwable $exception) {
            throw new RuntimeException('Gagal membuat transaksi Snap: '.$exception->getMessage(), 0, $exception);
        }
    }

    public function getTransactionStatus(string $midtransOrderId): array
    {
        $this->ensureConfigured();
        $this->configurePackage();

        try {
            return $this->toArray(MidtransTransaction::status($midtransOrderId));
        } catch (Throwable $exception) {
            throw new RuntimeException('Gagal mengambil status Midtrans: '.$exception->getMessage(), 0, $exception);
        }
    }

    public function validateBankAccount(string $bank, string $account): array
    {
        $this->ensureDisbursementConfigured();

        $response = $this->irisRequest()
            ->get('/account_validation', [
                'bank' => $bank,
                'account' => $account,
            ]);

        return $this->irisJson($response->json(), $response->status(), $response->body());
    }

    public function createPayout(array $payout, string $idempotencyKey): array
    {
        $this->ensureDisbursementConfigured();

        $response = $this->irisRequest()
            ->withHeaders(['X-Idempotency-Key' => Str::limit($idempotencyKey, 46, '')])
            ->post('/payouts', ['payouts' => [$payout]]);

        return $this->irisJson($response->json(), $response->status(), $response->body());
    }

    public function approvePayout(string $referenceNo): array
    {
        $this->ensureDisbursementConfigured();

        $response = $this->irisRequest((string) config('midtrans.disbursement.approver_key'))
            ->post('/payouts/approve', ['reference_nos' => [$referenceNo]]);

        return $this->irisJson($response->json(), $response->status(), $response->body());
    }

    public function getPayoutStatus(string $referenceNo): array
    {
        $this->ensureDisbursementConfigured();

        $response = $this->irisRequest()
            ->get('/payouts/'.rawurlencode($referenceNo));

        return $this->irisJson($response->json(), $response->status(), $response->body());
    }

    private function configurePackage(): void
    {
        MidtransConfig::$serverKey = (string) config('midtrans.server_key');
        MidtransConfig::$clientKey = (string) config('midtrans.client_key');
        MidtransConfig::$isProduction = $this->isProduction();
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;
        MidtransConfig::$curlOptions = [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ];

        if (filled(config('midtrans.notification_url'))) {
            MidtransConfig::$overrideNotifUrl = (string) config('midtrans.notification_url');
        }
    }

    private function ensureConfigured(): void
    {
        if ($error = $this->configurationError()) {
            throw new RuntimeException($error);
        }
    }

    private function ensureDisbursementConfigured(): void
    {
        $this->ensureConfigured();

        if (! config('midtrans.disbursement.enabled')) {
            throw new RuntimeException('Akses disbursement API belum diaktifkan untuk akun ini.');
        }

        if (blank(config('midtrans.disbursement.creator_key'))) {
            throw new RuntimeException('Kredensial disbursement API Midtrans belum diisi.');
        }
    }

    private function irisRequest(?string $key = null): PendingRequest
    {
        $key = $key ?: (string) config('midtrans.disbursement.creator_key');

        if (blank($key)) {
            throw new RuntimeException('Kunci disbursement API Midtrans belum diisi.');
        }

        return Http::baseUrl(rtrim((string) config('midtrans.disbursement.base_url'), '/'))
            ->withBasicAuth($key, '')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('midtrans.disbursement.timeout', 30));
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function irisJson(?array $json, int $status, string $body): array
    {
        if ($status < 200 || $status >= 300) {
            $message = data_get($json, 'error_message')
                ?: data_get($json, 'message')
                ?: 'Midtrans mengembalikan HTTP '.$status.'.';

            throw new RuntimeException('Disbursement API Midtrans gagal: '.$message);
        }

        if (! is_array($json)) {
            throw new RuntimeException('Respons disbursement API Midtrans tidak dapat dibaca: '.substr($body, 0, 200));
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $value): array
    {
        $encoded = json_encode($value);

        if ($encoded === false) {
            return [];
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }
}
