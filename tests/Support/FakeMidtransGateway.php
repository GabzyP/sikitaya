<?php

namespace Tests\Support;

use App\Contracts\MidtransGateway;
use RuntimeException;
use Throwable;

class FakeMidtransGateway implements MidtransGateway
{
    public bool $configured = true;

    public ?string $configurationError = null;

    public bool $production = false;

    /** @var array<string, mixed> */
    public array $snapResponse = [
        'token' => 'snap-token-test',
        'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-test',
    ];

    public ?Throwable $snapException = null;

    /** @var list<array<string, mixed>> */
    public array $snapPayloads = [];

    /** @var array<string, mixed> */
    public array $statusResponse = [];

    public ?Throwable $statusException = null;

    /** @var list<string> */
    public array $statusRequests = [];

    /** @var array<string, mixed> */
    public array $bankValidationResponse = [
        'status' => 'valid',
        'account_name' => null,
    ];

    public ?Throwable $bankValidationException = null;

    /** @var list<array{bank: string, account: string}> */
    public array $bankValidationRequests = [];

    /** @var array<string, mixed> */
    public array $payoutResponse = [
        'payouts' => [
            [
                'reference_no' => 'IRIS-REF-TEST-1',
                'status' => 'queued',
            ],
        ],
    ];

    public ?Throwable $payoutException = null;

    /** @var list<array<string, mixed>> */
    public array $payoutRequests = [];

    /** @var list<string> */
    public array $payoutIdempotencyKeys = [];

    /** @var array<string, mixed> */
    public array $approvalResponse = [
        'status' => 'processing',
    ];

    public ?Throwable $approvalException = null;

    /** @var list<string> */
    public array $approvalReferences = [];

    /** @var array<string, mixed> */
    public array $payoutStatusResponse = [
        'status' => 'processing',
    ];

    public ?Throwable $payoutStatusException = null;

    /** @var list<string> */
    public array $payoutStatusRequests = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function configurationError(): ?string
    {
        return $this->configurationError;
    }

    public function isProduction(): bool
    {
        return $this->production;
    }

    public function splitEnabled(): bool
    {
        return (bool) config('midtrans.split.enabled', false);
    }

    public function disbursementEnabled(): bool
    {
        return (bool) config('midtrans.disbursement.enabled', false);
    }

    public function createSnapTransaction(array $payload): array
    {
        $this->snapPayloads[] = $payload;

        if ($this->snapException) {
            throw $this->snapException;
        }

        return $this->snapResponse;
    }

    public function getTransactionStatus(string $midtransOrderId): array
    {
        $this->statusRequests[] = $midtransOrderId;

        if ($this->statusException) {
            throw $this->statusException;
        }

        if ($this->statusResponse === []) {
            throw new RuntimeException('FakeMidtransGateway::statusResponse belum diisi.');
        }

        return $this->statusResponse;
    }

    public function validateBankAccount(string $bank, string $account): array
    {
        $this->bankValidationRequests[] = ['bank' => $bank, 'account' => $account];

        if ($this->bankValidationException) {
            throw $this->bankValidationException;
        }

        return $this->bankValidationResponse;
    }

    public function createPayout(array $payout, string $idempotencyKey): array
    {
        $this->payoutRequests[] = $payout;
        $this->payoutIdempotencyKeys[] = $idempotencyKey;

        if ($this->payoutException) {
            throw $this->payoutException;
        }

        return $this->payoutResponse;
    }

    public function approvePayout(string $referenceNo): array
    {
        $this->approvalReferences[] = $referenceNo;

        if ($this->approvalException) {
            throw $this->approvalException;
        }

        return $this->approvalResponse;
    }

    public function getPayoutStatus(string $referenceNo): array
    {
        $this->payoutStatusRequests[] = $referenceNo;

        if ($this->payoutStatusException) {
            throw $this->payoutStatusException;
        }

        return $this->payoutStatusResponse;
    }
}
