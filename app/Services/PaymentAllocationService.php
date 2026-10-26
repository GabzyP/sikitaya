<?php

namespace App\Services;

use App\Contracts\MidtransGateway;
use App\Enums\PaymentAllocationStatus;
use App\Models\PaymentAllocation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PaymentAllocationService
{
    public function __construct(private readonly MidtransGateway $gateway) {}

    public function approve(PaymentAllocation $allocation, User $admin): void
    {
        DB::transaction(function () use ($allocation, $admin) {
            $locked = PaymentAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('transaction');

            if (! $locked->isDisbursement()) {
                throw ValidationException::withMessages(['allocation' => 'Hanya alokasi disbursement yang memerlukan persetujuan admin.']);
            }

            if (! $locked->transaction?->isPaid()) {
                throw ValidationException::withMessages(['allocation' => 'Alokasi hanya dapat disetujui setelah pembayaran gabungan lunas.']);
            }

            if (! $locked->status->canApprove()) {
                throw ValidationException::withMessages(['allocation' => 'Status alokasi tidak dapat disetujui saat ini.']);
            }

            $before = $locked->status;
            $locked->update([
                'status' => PaymentAllocationStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'last_error' => null,
            ]);

            $this->recordEvent($locked, 'approved', $before, PaymentAllocationStatus::Approved, $admin, 'Admin menyetujui alokasi untuk dicairkan.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function send(PaymentAllocation $allocation, User $admin): array
    {
        $allocation = DB::transaction(function () use ($allocation, $admin) {
            $locked = PaymentAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('transaction', 'store.owner');

            if (! $locked->isDisbursement()) {
                throw ValidationException::withMessages(['allocation' => 'Alokasi transaction split diproses otomatis oleh Midtrans.']);
            }

            if (! $locked->transaction?->isPaid()) {
                throw ValidationException::withMessages(['allocation' => 'Dana hanya dapat dikirim setelah pembayaran gabungan lunas.']);
            }

            if (! $locked->status->canSend()) {
                throw ValidationException::withMessages(['allocation' => 'Status alokasi tidak dapat dikirim. Setujui alokasi terlebih dahulu.']);
            }

            if (! $this->gateway->disbursementEnabled()) {
                throw ValidationException::withMessages(['allocation' => 'Akses disbursement API Midtrans belum aktif. Pencairan tidak dapat dilakukan.']);
            }

            if ($locked->net_amount <= $locked->disbursed_amount) {
                throw ValidationException::withMessages(['allocation' => 'Alokasi ini sudah tidak memiliki sisa dana yang dapat dicairkan.']);
            }

            if (blank($locked->payout_bank_code) || blank($locked->payout_account_number) || blank($locked->payout_account_name)) {
                throw ValidationException::withMessages(['allocation' => 'Rekening penerima belum lengkap.']);
            }

            if (! $locked->store?->hasVerifiedPayoutAccount()) {
                throw ValidationException::withMessages(['allocation' => 'Rekening penerima belum diverifikasi admin.']);
            }

            if ((string) $locked->store->payout_bank_code !== (string) $locked->payout_bank_code
                || (string) $locked->store->payout_account_number !== (string) $locked->payout_account_number
                || (string) $locked->store->payout_account_name !== (string) $locked->payout_account_name) {
                throw ValidationException::withMessages([
                    'allocation' => 'Rekening penerima pada alokasi berbeda dari rekening toko yang terverifikasi. Buat transaksi baru setelah rekening diperbarui.',
                ]);
            }

            $before = $locked->status;
            $attempt = $locked->attempt_count + 1;

            // Pakai ulang referensi lokal saat mencoba lagi setelah gagal agar
            // Idempotency-Key Iris tetap sama bila percobaan sebelumnya ambigu.
            $previousIdempotencyKey = $before === PaymentAllocationStatus::Failed
                ? (string) (data_get($locked->metadata, 'disbursement_idempotency_key') ?: '')
                : '';

            $reference = $previousIdempotencyKey !== ''
                ? $previousIdempotencyKey
                : 'DISB-'.$locked->id.'-'.$attempt.'-'.Str::lower(Str::random(4));

            $locked->update([
                'status' => PaymentAllocationStatus::Processing,
                'attempt_count' => $attempt,
                'requested_by' => $admin->id,
                'requested_at' => now(),
                'processed_at' => now(),
                'failed_at' => null,
                'last_error' => null,
                'midtrans_reference' => $reference,
                'midtrans_status' => 'requesting',
                'metadata' => array_merge($locked->metadata ?? [], [
                    'disbursement_idempotency_key' => $reference,
                ]),
            ]);

            $this->recordEvent($locked, 'disbursement_requested', $before, PaymentAllocationStatus::Processing, $admin, 'Permintaan pencairan dikirim ke Midtrans.', [
                'reference' => $reference,
                'amount' => $locked->net_amount,
            ]);

            return $locked->fresh(['transaction', 'store.owner']);
        });

        $reference = (string) $allocation->midtrans_reference;
        $payoutCreated = false;

        try {
            $response = $this->gateway->createPayout([
                'beneficiary_name' => $allocation->payout_account_name,
                'beneficiary_account' => $allocation->payout_account_number,
                'beneficiary_bank' => $allocation->payout_bank_code,
                'beneficiary_email' => $allocation->store?->owner?->email
                    ?: config('midtrans.disbursement.default_email'),
                'amount' => (string) $allocation->net_amount,
                'notes' => 'Pencairan pesanan '.$allocation->order->order_number.' - Sikitaya',
            ], $reference);

            $payoutCreated = true;
            $payout = data_get($response, 'payouts.0', $response);
            $reference = (string) (data_get($payout, 'reference_no') ?: $reference);
            $gatewayStatus = strtolower((string) (data_get($payout, 'status') ?: 'queued'));

            $allocation->update([
                'midtrans_reference' => $reference,
                'midtrans_status' => $gatewayStatus,
                'metadata' => array_merge($allocation->metadata ?? [], [
                    'disbursement_response' => $response,
                ]),
            ]);

            $this->recordEvent($allocation, 'disbursement_created', PaymentAllocationStatus::Processing, PaymentAllocationStatus::Processing, $admin, 'Midtrans menerima permintaan pencairan.', [
                'reference' => $reference,
                'gateway_status' => $gatewayStatus,
            ]);

            if ((bool) config('midtrans.disbursement.auto_approve')) {
                if (blank(config('midtrans.disbursement.approver_key'))) {
                    throw new RuntimeException('Payout dibuat, tetapi MIDTRANS_IRIS_APPROVER_KEY belum diisi. Setujui payout melalui dashboard Midtrans.');
                }

                $approval = $this->gateway->approvePayout($reference);

                $allocation->update([
                    'midtrans_status' => strtolower((string) (data_get($approval, 'status') ?: 'processing')),
                    'metadata' => array_merge($allocation->metadata ?? [], [
                        'disbursement_approval_response' => $approval,
                    ]),
                ]);

                $this->recordEvent($allocation, 'disbursement_approved', PaymentAllocationStatus::Processing, PaymentAllocationStatus::Processing, $admin, 'Payout disetujui melalui API Midtrans.');
            }

            if ($this->gatewayStatusIsCompleted($allocation->midtrans_status)) {
                $this->markCompleted($allocation, 'Pencairan dikonfirmasi selesai oleh Midtrans.');
            } elseif ($this->gatewayStatusIsFailed($allocation->midtrans_status)) {
                $this->markFailed($allocation, 'Midtrans menolak atau menggagalkan pencairan.');
            }

            return ['reference' => $reference, 'status' => $allocation->fresh()->status->value];
        } catch (Throwable $exception) {
            $message = Str::limit($exception->getMessage(), 1000, '');

            if (! $payoutCreated) {
                $this->markFailed($allocation, $message);
            } else {
                $allocation->update([
                    'midtrans_status' => 'approval_pending',
                    'last_error' => $message,
                ]);

                $this->recordEvent($allocation, 'approval_failed', PaymentAllocationStatus::Processing, PaymentAllocationStatus::Processing, auth()->user(), $message);
            }

            throw new RuntimeException($message, 0, $exception);
        }
    }

    public function sync(PaymentAllocation $allocation): array
    {
        if ($allocation->status === PaymentAllocationStatus::Completed) {
            return [
                'status' => PaymentAllocationStatus::Completed->value,
                'gateway_status' => $allocation->midtrans_status,
            ];
        }

        if (blank($allocation->midtrans_reference)) {
            throw ValidationException::withMessages(['allocation' => 'Alokasi belum memiliki referensi pencairan Midtrans.']);
        }

        if (! $this->gateway->disbursementEnabled()) {
            throw ValidationException::withMessages(['allocation' => 'Akses disbursement API Midtrans belum aktif.']);
        }

        $response = $this->gateway->getPayoutStatus((string) $allocation->midtrans_reference);
        $payout = data_get($response, 'payouts.0', $response);
        $status = strtolower((string) (data_get($payout, 'status') ?: 'processing'));

        $allocation->update([
            'midtrans_status' => $status,
            'metadata' => array_merge($allocation->metadata ?? [], [
                'disbursement_status_response' => $response,
            ]),
        ]);

        if ($this->gatewayStatusIsCompleted($status)) {
            $this->markCompleted($allocation, 'Pencairan dikonfirmasi selesai oleh Midtrans.');
        } elseif ($this->gatewayStatusIsFailed($status)) {
            $this->markFailed($allocation, (string) (data_get($payout, 'failure_reason') ?: 'Midtrans menyatakan pencairan gagal.'));
        }

        return ['status' => $allocation->fresh()->status->value, 'gateway_status' => $status];
    }

    public function verifyStoreAccount(Store $store, User $admin): Store
    {
        if (blank($store->payout_bank_code) || blank($store->payout_account_number) || blank($store->payout_account_name)) {
            throw ValidationException::withMessages(['payout' => 'Lengkapi bank, nomor rekening, dan nama pemilik sebelum validasi.']);
        }

        if (! $this->gateway->disbursementEnabled()) {
            throw ValidationException::withMessages(['payout' => 'Akses disbursement API Midtrans belum aktif, validasi rekening tidak dapat dilakukan.']);
        }

        $response = $this->gateway->validateBankAccount(
            (string) $store->payout_bank_code,
            (string) $store->payout_account_number,
        );

        $errorMessage = (string) (data_get($response, 'error_message') ?: '');
        $validatedName = (string) (data_get($response, 'account_name') ?: data_get($response, 'beneficiary_name') ?: '');
        $validatedAccount = (string) (data_get($response, 'account_no') ?: data_get($response, 'account_number') ?: '');
        $status = strtolower((string) (data_get($response, 'status') ?: 'valid'));

        if ($errorMessage !== '' || ! in_array($status, ['valid', 'verified', 'success', 'ok'], true)) {
            throw ValidationException::withMessages([
                'payout' => 'Midtrans tidak menyatakan rekening valid: '.($errorMessage !== '' ? $errorMessage : $status),
            ]);
        }

        if ($validatedAccount !== '' && preg_replace('/\D+/', '', $validatedAccount) !== (string) $store->payout_account_number) {
            throw ValidationException::withMessages(['payout' => 'Nomor rekening hasil validasi Midtrans tidak cocok dengan data toko.']);
        }

        if ($validatedName === '') {
            throw ValidationException::withMessages(['payout' => 'Midtrans tidak mengembalikan nama pemilik rekening. Verifikasi dibatalkan.']);
        }

        if ($this->normalizeName($validatedName) !== $this->normalizeName((string) $store->payout_account_name)) {
            throw ValidationException::withMessages(['payout' => 'Nama pemilik rekening tidak cocok dengan hasil validasi Midtrans.']);
        }

        $store->update([
            'payout_account_name' => $validatedName,
            'payout_account_verified_at' => now(),
            'payout_account_verified_by' => $admin->id,
        ]);

        return $store->refresh();
    }

    public function markCompleted(PaymentAllocation $allocation, string $message): void
    {
        DB::transaction(function () use ($allocation, $message) {
            $locked = PaymentAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentAllocationStatus::Completed) {
                return;
            }

            $before = $locked->status;
            $locked->update([
                'status' => PaymentAllocationStatus::Completed,
                'midtrans_status' => 'completed',
                'completed_at' => now(),
                'failed_at' => null,
                'disbursed_amount' => $locked->net_amount,
                'last_error' => null,
            ]);

            $this->recordEvent($locked, 'disbursement_completed', $before, PaymentAllocationStatus::Completed, auth()->user(), $message);
        });
    }

    public function markFailed(PaymentAllocation $allocation, string $message): void
    {
        DB::transaction(function () use ($allocation, $message) {
            $locked = PaymentAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentAllocationStatus::Completed) {
                return;
            }

            $before = $locked->status;
            $locked->update([
                'status' => PaymentAllocationStatus::Failed,
                'midtrans_status' => 'failed',
                'failed_at' => now(),
                'last_error' => $message,
            ]);

            $this->recordEvent($locked, 'disbursement_failed', $before, PaymentAllocationStatus::Failed, auth()->user(), $message);
        });
    }

    private function gatewayStatusIsCompleted(string $status): bool
    {
        return in_array(strtolower($status), ['completed', 'complete', 'success', 'succeeded', 'settled'], true);
    }

    private function gatewayStatusIsFailed(string $status): bool
    {
        return in_array(strtolower($status), ['failed', 'failure', 'rejected', 'cancelled', 'canceled'], true);
    }

    private function normalizeName(string $name): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '');
    }

    private function recordEvent(
        PaymentAllocation $allocation,
        string $action,
        PaymentAllocationStatus $before,
        PaymentAllocationStatus $after,
        ?User $actor,
        string $message,
        array $payload = [],
    ): void {
        $allocation->events()->create([
            'action' => $action,
            'status_before' => $before->value,
            'status_after' => $after->value,
            'actor_id' => $actor?->id,
            'message' => $message,
            'payload' => $payload ?: null,
        ]);
    }
}
