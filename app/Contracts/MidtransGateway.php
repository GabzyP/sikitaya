<?php

namespace App\Contracts;

interface MidtransGateway
{
    public function isConfigured(): bool;

    public function configurationError(): ?string;

    public function isProduction(): bool;

    public function splitEnabled(): bool;

    public function disbursementEnabled(): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSnapTransaction(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function getTransactionStatus(string $midtransOrderId): array;

    /**
     * @return array<string, mixed>
     */
    public function validateBankAccount(string $bank, string $account): array;

    /**
     * @param  array<string, mixed>  $payout
     * @return array<string, mixed>
     */
    public function createPayout(array $payout, string $idempotencyKey): array;

    /**
     * @return array<string, mixed>
     */
    public function approvePayout(string $referenceNo): array;

    /**
     * @return array<string, mixed>
     */
    public function getPayoutStatus(string $referenceNo): array;
}
