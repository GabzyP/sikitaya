<?php

namespace App\Services;

use App\Contracts\MidtransGateway;
use App\Enums\PaymentAllocationMethod;
use App\Enums\PaymentAllocationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationEvent;
use App\Models\PaymentAttempt;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MidtransPaymentService
{
    public function __construct(private readonly MidtransGateway $gateway) {}

    /**
     * Status ketersediaan jalur pembayaran online untuk kumpulan toko.
     *
     * @param  Collection<int, Store>|EloquentCollection<int, Store>  $stores
     * @return array{
     *     available: bool,
     *     route: ?string,
     *     configured: bool,
     *     environment: string,
     *     reason: ?string,
     *     split_ready: bool,
     *     disbursement_ready: bool,
     *     missing_split_stores: array<int, string>,
     *     missing_payout_stores: array<int, string>
     * }
     */
    public function availability(Collection|EloquentCollection $stores): array
    {
        $stores = $stores->unique('id')->values();
        $configured = $this->gateway->isConfigured();
        $environment = $this->gateway->isProduction() ? 'production' : 'sandbox';

        $missingSplit = $stores
            ->reject(fn (Store $store) => $store->isReadyForSplit())
            ->pluck('name')
            ->all();

        $missingPayout = $stores
            ->reject(fn (Store $store) => $store->hasVerifiedPayoutAccount())
            ->pluck('name')
            ->all();

        $platformMerchantReady = filled(config('midtrans.platform_merchant_id'));
        $splitReady = $configured
            && $this->gateway->splitEnabled()
            && $platformMerchantReady
            && $missingSplit === [];

        $disbursementReady = $configured
            && $this->gateway->disbursementEnabled()
            && $missingPayout === [];

        $route = null;
        $reason = null;

        if (! $configured) {
            $reason = $this->gateway->configurationError();
        } elseif ($splitReady) {
            $route = PaymentAllocationMethod::Split->value;
        } elseif ($disbursementReady) {
            $route = PaymentAllocationMethod::Disbursement->value;
        } elseif (! $this->gateway->splitEnabled() && ! $this->gateway->disbursementEnabled()) {
            $reason = 'Akses Partner transaction split dan disbursement API belum diaktifkan untuk akun Midtrans Sikitaya. Hubungi Midtrans untuk mengaktifkan salah satu jalur otomatis.';
        } elseif ($this->gateway->splitEnabled() && ! $platformMerchantReady) {
            $reason = 'MIDTRANS_PLATFORM_MERCHANT_ID belum diisi, sehingga transaction split belum dapat dipakai.';
        } elseif ($this->gateway->splitEnabled() && $missingSplit !== [] && $this->gateway->disbursementEnabled() && $missingPayout !== []) {
            $reason = 'Sebagian toko belum memiliki merchant ID terdaftar atau rekening penerima terverifikasi.';
        } elseif ($this->gateway->splitEnabled() && $missingSplit !== []) {
            $reason = 'Merchant ID Midtrans belum siap untuk toko: '.implode(', ', $missingSplit).'.';
        } else {
            $reason = 'Rekening penerima disbursement belum terverifikasi untuk toko: '.implode(', ', $missingPayout).'.';
        }

        return [
            'available' => $route !== null,
            'route' => $route,
            'configured' => $configured,
            'environment' => $environment,
            'reason' => $reason,
            'split_ready' => $splitReady,
            'disbursement_ready' => $disbursementReady,
            'missing_split_stores' => $missingSplit,
            'missing_payout_stores' => $missingPayout,
        ];
    }

    /**
     * Membuat transaksi gabungan dan alokasi per pesanan di dalam transaksi DB
     * yang sama dengan pembuatan pesanan.
     *
     * @param  Collection<int, Order>|EloquentCollection<int, Order>  $orders
     */
    public function createForOrders(User $buyer, Collection|EloquentCollection $orders, string $checkoutToken): PaymentTransaction
    {
        $stores = $orders->map(fn (Order $order) => $order->store)->filter()->unique('id')->values();
        $availability = $this->availability($stores);

        if (! $availability['available']) {
            throw ValidationException::withMessages([
                'payment_method' => $availability['reason'] ?? 'Pembayaran Midtrans belum tersedia.',
            ]);
        }

        $grossAmount = (int) $orders->sum('total');
        $this->assertOrderTotals($orders, $grossAmount);

        $transaction = PaymentTransaction::create([
            'reference' => $this->nextReference(),
            'checkout_token' => $checkoutToken,
            'user_id' => $buyer->id,
            'provider' => 'midtrans',
            'allocation_method' => $availability['route'],
            'status' => PaymentTransactionStatus::Pending,
            'currency' => 'IDR',
            'gross_amount' => $grossAmount,
            'platform_commission' => 0,
            'midtrans_fee' => 0,
            'fee_bearer' => 'platform',
            'expires_at' => now()->addMinutes((int) config('midtrans.expiry_minutes', 1440)),
        ]);

        foreach ($orders as $order) {
            $this->createAllocation($transaction, $order);
            $order->update(['payment_transaction_id' => $transaction->id]);
        }

        return $transaction;
    }

    public function startPayment(PaymentTransaction $transaction): PaymentAttempt
    {
        if ($transaction->isPaid()) {
            throw new RuntimeException('Transaksi ini sudah lunas.');
        }

        if ($transaction->isTerminal()) {
            throw new RuntimeException('Transaksi sudah ditutup. Gunakan pembayaran ulang untuk membuka transaksi baru.');
        }

        $attempt = $transaction->attempts()->latest('attempt_number')->first();

        if ($attempt?->isUsable()) {
            return $attempt;
        }

        $attemptNumber = ((int) $transaction->attempts()->max('attempt_number')) + 1;
        $midtransOrderId = $this->midtransOrderId($transaction, $attemptNumber);
        $expiresAt = now()->addMinutes((int) config('midtrans.expiry_minutes', 1440));

        $attempt = $transaction->attempts()->create([
            'attempt_number' => $attemptNumber,
            'midtrans_order_id' => $midtransOrderId,
            'status' => 'creating',
            'expires_at' => $expiresAt,
        ]);

        $transaction->update([
            'midtrans_order_id' => $midtransOrderId,
            'expires_at' => $expiresAt,
            'failure_reason' => null,
        ]);

        try {
            $payload = $this->snapPayload($transaction);
            $response = $this->gateway->createSnapTransaction($payload);

            if (blank($response['token'] ?? null) || blank($response['redirect_url'] ?? null)) {
                throw new RuntimeException('Respons Snap tidak memuat token atau tautan pembayaran.');
            }

            $attempt->update([
                'status' => 'created',
                'snap_token' => $response['token'] ?? null,
                'snap_redirect_url' => $response['redirect_url'] ?? null,
                'gateway_status' => 'snap_created',
                'response' => $response,
                'error' => null,
            ]);

            $transaction->update([
                'snap_token' => $response['token'] ?? null,
                'snap_redirect_url' => $response['redirect_url'] ?? null,
                'gateway_status' => 'snap_created',
                'failure_reason' => null,
            ]);
        } catch (Throwable $exception) {
            $message = Str::limit($exception->getMessage(), 1000, '');

            $attempt->update([
                'status' => 'failed',
                'gateway_status' => 'snap_failed',
                'error' => $message,
            ]);

            $transaction->update([
                'gateway_status' => 'snap_failed',
                'failure_reason' => $message,
            ]);
        }

        return $attempt->refresh();
    }

    /**
     * Membuat percobaan pembayaran baru. Untuk transaksi terminal yang belum
     * dibayar, stok dipesan ulang sebelum Snap baru dibuat.
     */
    public function retryPayment(PaymentTransaction $transaction): PaymentAttempt
    {
        $transaction = DB::transaction(function () use ($transaction) {
            $locked = PaymentTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                throw ValidationException::withMessages(['payment' => 'Transaksi ini sudah lunas.']);
            }

            if (! $locked->status->canRetry()) {
                throw ValidationException::withMessages(['payment' => 'Transaksi ini tidak dapat dibayar ulang.']);
            }

            $usableAttempt = $locked->attempts()->latest('attempt_number')->first();

            if ($locked->isPending() && $usableAttempt?->isUsable()) {
                return $locked;
            }

            if ($locked->isTerminal()) {
                $this->reserveStockAgain($locked);

                // Percobaan lama tidak boleh dipakai ulang setelah transaksi
                // terminal. Buat ID Midtrans baru agar callback lama ditolak.
                $locked->attempts()
                    ->where('status', 'created')
                    ->update(['status' => 'closed']);
            }

            $locked->update([
                'status' => PaymentTransactionStatus::Pending,
                'failure_reason' => null,
                'gateway_status' => 'retrying',
                'stock_restored_at' => null,
                'midtrans_transaction_id' => null,
                'payment_type' => null,
                'fraud_status' => null,
                'snap_token' => null,
                'snap_redirect_url' => null,
                'expires_at' => now()->addMinutes((int) config('midtrans.expiry_minutes', 1440)),
            ]);

            $locked->allocations()->get()->each(function (PaymentAllocation $allocation) {
                $before = $allocation->status;
                $allocation->update([
                    'status' => PaymentAllocationStatus::Pending,
                    'midtrans_status' => null,
                    'failed_at' => null,
                    'last_error' => null,
                ]);

                $this->recordEvent(
                    $allocation,
                    'payment_reopened',
                    $before->value,
                    PaymentAllocationStatus::Pending->value,
                    null,
                    'Pembeli membuat percobaan pembayaran baru setelah transaksi terminal.'
                );
            });

            return $locked->refresh();
        });

        return $this->startPayment($transaction);
    }

    /**
     * Sinkronkan status dari Get Status API Midtrans.
     *
     * @return array<string, mixed>
     */
    public function syncStatus(PaymentTransaction $transaction): array
    {
        $midtransOrderId = $transaction->midtrans_order_id
            ?: $transaction->attempts()->latest('attempt_number')->value('midtrans_order_id');

        if (blank($midtransOrderId)) {
            throw new RuntimeException('Transaksi belum memiliki ID Midtrans untuk dicek.');
        }

        $payload = $this->gateway->getTransactionStatus($midtransOrderId);

        return $this->applyGatewayStatus($transaction, $payload, 'status_api');
    }

    /**
     * Validasi isi payload gateway terhadap catatan internal.
     *
     * @param  array<string, mixed>  $payload
     */
    public function assertPayloadMatchesTransaction(PaymentTransaction $transaction, array $payload): void
    {
        $grossAmount = $payload['gross_amount'] ?? null;

        if ($grossAmount === null || (int) round((float) $grossAmount) !== $transaction->gross_amount) {
            throw new \DomainException('Nominal notifikasi Midtrans tidak sesuai dengan transaksi tersimpan.');
        }

        $currency = strtoupper((string) ($payload['currency'] ?? 'IDR'));

        if ($currency !== strtoupper($transaction->currency)) {
            throw new \DomainException('Mata uang notifikasi Midtrans tidak sesuai.');
        }

        $orderId = $payload['order_id'] ?? null;

        if (filled($orderId)) {
            $latestOrderId = $transaction->attempts()
                ->latest('attempt_number')
                ->value('midtrans_order_id');

            if (filled($latestOrderId) && (string) $orderId !== (string) $latestOrderId) {
                throw new \DomainException('ID pesanan Midtrans bukan percobaan pembayaran terbaru untuk transaksi ini.');
            }

            $matches = $transaction->attempts()
                ->where('midtrans_order_id', $orderId)
                ->exists();

            if (! $matches) {
                throw new \DomainException('ID pesanan Midtrans tidak terhubung dengan transaksi ini.');
            }
        }
    }

    /**
     * Menerapkan status tervalidasi secara idempoten.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyGatewayStatus(PaymentTransaction $transaction, array $payload, string $source = 'notification'): array
    {
        $this->assertPayloadMatchesTransaction($transaction, $payload);

        $gatewayStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));
        $transactionId = $payload['transaction_id'] ?? null;

        return DB::transaction(function () use ($transaction, $payload, $source, $gatewayStatus, $fraudStatus, $transactionId) {
            $locked = PaymentTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $alreadyTerminal = $locked->status->isTerminal();

            $locked->forceFill([
                'gateway_payload' => $payload,
                // Status gateway transaksi terminal tidak boleh diubah oleh
                // callback terlambat yang bisa membuat status lokal "hidup" lagi.
                'gateway_status' => $alreadyTerminal
                    ? $locked->gateway_status
                    : ($gatewayStatus ?: $locked->gateway_status),
                'payment_type' => $payload['payment_type'] ?? $locked->payment_type,
                'fraud_status' => $fraudStatus ?: $locked->fraud_status,
                'notified_at' => now(),
                'notification_count' => $locked->notification_count + 1,
                'midtrans_transaction_id' => $locked->midtrans_transaction_id ?: $transactionId,
            ])->save();

            if ($locked->isPaid()) {
                return [
                    'outcome' => 'already_paid',
                    'status' => $locked->status->value,
                    'message' => 'Pembayaran sudah tercatat lunas; callback tidak mengubah data.',
                ];
            }

            if ($this->isSuccessfulStatus($gatewayStatus, $fraudStatus)) {
                if ($locked->status->isTerminal()) {
                    return [
                        'outcome' => 'ignored_terminal',
                        'status' => $locked->status->value,
                        'message' => 'Status lunas diabaikan karena transaksi sudah terminal.',
                    ];
                }

                $this->markPaid($locked, $payload);

                return [
                    'outcome' => 'paid',
                    'status' => PaymentTransactionStatus::Paid->value,
                    'message' => 'Pembayaran berhasil dan seluruh pesanan terkait telah ditandai dibayar.',
                ];
            }

            if ($this->isPendingStatus($gatewayStatus, $fraudStatus)) {
                if ($alreadyTerminal) {
                    return [
                        'outcome' => 'ignored_terminal',
                        'status' => $locked->status->value,
                        'message' => 'Status pending diabaikan karena transaksi sudah terminal.',
                    ];
                }

                $locked->forceFill([
                    'status' => PaymentTransactionStatus::Pending,
                    'failure_reason' => $gatewayStatus === 'capture' && $fraudStatus === 'challenge'
                        ? 'Pembayaran menunggu keputusan fraud dari Midtrans.'
                        : null,
                ])->save();

                return [
                    'outcome' => 'pending',
                    'status' => PaymentTransactionStatus::Pending->value,
                    'message' => 'Pembayaran masih menunggu konfirmasi Midtrans.',
                ];
            }

            $terminalStatus = $this->terminalStatus($gatewayStatus, $fraudStatus);

            if ($terminalStatus === null) {
                return [
                    'outcome' => 'ignored_unknown',
                    'status' => $locked->status->value,
                    'message' => 'Status Midtrans tidak dikenali: '.($gatewayStatus ?: '-'),
                ];
            }

            if ($alreadyTerminal) {
                return [
                    'outcome' => 'ignored_terminal',
                    'status' => $locked->status->value,
                    'message' => 'Status terminal baru diabaikan karena transaksi sudah ditutup.',
                ];
            }

            $this->markTerminal($locked, $terminalStatus, $payload, $source);

            return [
                'outcome' => $terminalStatus->value,
                'status' => $terminalStatus->value,
                'message' => 'Transaksi ditandai '.$terminalStatus->label().'; stok dipulihkan sekali.',
            ];
        });
    }

    /**
     * Menutup transaksi pending secara lokal ketika Snap tidak pernah dibuat
     * dan batas waktunya habis. Transaksi dengan ID Midtrans harus dicek lewat
     * Get Status terlebih dahulu oleh command.
     */
    public function closeWithoutGateway(PaymentTransaction $transaction, PaymentTransactionStatus $status, string $reason): void
    {
        DB::transaction(function () use ($transaction, $status, $reason) {
            $locked = PaymentTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid() || $locked->isTerminal()) {
                return;
            }

            $this->markTerminal($locked, $status, [
                'transaction_status' => $status->value,
                'status_message' => $reason,
            ], 'scheduler');
        });
    }

    /**
     * @param  Collection<int, Order>|EloquentCollection<int, Order>  $orders
     */
    private function createAllocation(PaymentTransaction $transaction, Order $order): PaymentAllocation
    {
        $store = $order->store;
        $sellerId = $store?->user_id;

        if (! $store || ! $sellerId) {
            throw new RuntimeException('Toko atau penjual untuk pesanan '.$order->order_number.' tidak ditemukan.');
        }

        $method = $transaction->allocation_method;
        $isSplit = $method === PaymentAllocationMethod::Split;

        $allocation = $transaction->allocations()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'seller_id' => $sellerId,
            'method' => $method,
            'status' => PaymentAllocationStatus::Pending,
            'gross_amount' => $order->total,
            'platform_commission' => 0,
            'midtrans_fee' => 0,
            'net_amount' => $order->total,
            'merchant_id' => $isSplit ? $store->midtrans_merchant_id : null,
            'payout_bank_code' => $isSplit ? null : $store->payout_bank_code,
            'payout_bank_name' => $isSplit ? null : $store->payout_bank_name,
            'payout_account_number' => $isSplit ? null : $store->payout_account_number,
            'payout_account_name' => $isSplit ? null : $store->payout_account_name,
            'metadata' => [
                'order_number' => $order->order_number,
                'store_name' => $store->name,
                'commission_percent' => 0,
                'fee_bearer' => 'platform',
            ],
        ]);

        $this->recordEvent($allocation, 'created', null, PaymentAllocationStatus::Pending->value, null, 'Alokasi dibuat saat checkout.');

        return $allocation;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapPayload(PaymentTransaction $transaction): array
    {
        $transaction->loadMissing(['user', 'orders.store', 'allocations']);

        $items = $transaction->orders->map(function (Order $order) {
            return [
                'id' => $order->order_number,
                'price' => (int) $order->total,
                'quantity' => 1,
                'name' => Str::limit('Pesanan '.$order->store->name, 50, ''),
            ];
        })->values()->all();

        $itemTotal = (int) collect($items)->sum(fn (array $item) => $item['price'] * $item['quantity']);

        if ($itemTotal !== $transaction->gross_amount) {
            throw new RuntimeException('Total rincian item Midtrans tidak sama dengan total transaksi.');
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $transaction->midtrans_order_id,
                'gross_amount' => $transaction->gross_amount,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => Str::limit($transaction->user->name, 50, ''),
                'email' => $transaction->user->email,
                'phone' => $transaction->user->phone,
            ],
            'callbacks' => [
                'finish' => route('payments.show', ['paymentTransaction' => $transaction, 'status' => 'finish']),
                'unfinish' => route('payments.show', ['paymentTransaction' => $transaction, 'status' => 'unfinish']),
                'error' => route('payments.show', ['paymentTransaction' => $transaction, 'status' => 'error']),
            ],
        ];

        if (config('midtrans.expiry_minutes', 0) > 0) {
            $payload['expiry'] = [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => (int) config('midtrans.expiry_minutes'),
            ];
        }

        if ($transaction->allocation_method === PaymentAllocationMethod::Split) {
            $payload['transaction_split_settings'] = $this->splitSettings($transaction);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function splitSettings(PaymentTransaction $transaction): array
    {
        $platformMerchantId = (string) config('midtrans.platform_merchant_id');

        if (blank($platformMerchantId)) {
            throw new RuntimeException('Merchant ID platform Midtrans belum diisi.');
        }

        $rules = $transaction->allocations->map(function (PaymentAllocation $allocation) {
            if (blank($allocation->merchant_id)) {
                throw new RuntimeException('Merchant ID toko '.$allocation->store_id.' belum diisi.');
            }

            return [
                'merchant_id' => (string) $allocation->merchant_id,
                'value' => (string) $allocation->gross_amount,
            ];
        })->values()->all();

        $splitTotal = (int) collect($rules)->sum(fn (array $rule) => (int) $rule['value']);

        if ($splitTotal !== $transaction->gross_amount) {
            throw new RuntimeException('Total aturan transaction split tidak sama dengan nilai transaksi.');
        }

        if (collect($rules)->where('merchant_id', $platformMerchantId)->isNotEmpty()) {
            throw new RuntimeException('Merchant platform tidak boleh menjadi penerima alokasi penjual.');
        }

        return [
            'gross_amount_split' => [
                'type' => 'fixed',
                'split_rules' => $rules,
                'remaining_split_receiver' => $platformMerchantId,
            ],
            'fee_split' => [
                'type' => 'percentage',
                'split_rules' => [
                    [
                        'merchant_id' => $platformMerchantId,
                        'value' => '100',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markPaid(PaymentTransaction $transaction, array $payload): void
    {
        $paidAt = filled($payload['settlement_time'] ?? null)
            ? Carbon::parse($payload['settlement_time'])
            : now();

        $transaction->forceFill([
            'status' => PaymentTransactionStatus::Paid,
            'paid_at' => $paidAt,
            'midtrans_transaction_id' => $payload['transaction_id'] ?? $transaction->midtrans_transaction_id,
        ])->save();

        Order::where('payment_transaction_id', $transaction->id)
            ->where('status', 'menunggu')
            ->update([
                'status' => 'dibayar',
                'paid_at' => $paidAt,
                'updated_at' => now(),
            ]);

        $transaction->allocations()->with('order')->get()->each(function (PaymentAllocation $allocation) use ($paidAt) {
            if ($allocation->isSplit()) {
                $allocation->update([
                    'status' => PaymentAllocationStatus::Completed,
                    'midtrans_status' => 'settled',
                    'processed_at' => $paidAt,
                    'completed_at' => $paidAt,
                    'disbursed_amount' => $allocation->net_amount,
                    'metadata' => array_merge($allocation->metadata ?? [], [
                        'reconciliation' => 'Transaksi split mengikuti settlement Midtrans dan dialokasikan otomatis ke merchant toko.',
                    ]),
                ]);

                $this->recordEvent(
                    $allocation,
                    'split_settled',
                    PaymentAllocationStatus::Pending->value,
                    PaymentAllocationStatus::Completed->value,
                    null,
                    'Transaction split diselesaikan otomatis oleh Midtrans setelah settlement.'
                );
            } else {
                $allocation->update([
                    'status' => PaymentAllocationStatus::Ready,
                    'midtrans_status' => 'awaiting_disbursement',
                    'metadata' => array_merge($allocation->metadata ?? [], [
                        'reconciliation' => 'Dana gabungan telah lunas dan menunggu persetujuan admin untuk disbursement.',
                    ]),
                ]);

                $this->recordEvent(
                    $allocation,
                    'ready_for_disbursement',
                    PaymentAllocationStatus::Pending->value,
                    PaymentAllocationStatus::Ready->value,
                    null,
                    'Pembayaran lunas; alokasi siap ditinjau admin.'
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markTerminal(PaymentTransaction $transaction, PaymentTransactionStatus $status, array $payload, string $source): void
    {
        $reason = $payload['status_message']
            ?? $payload['status_code']
            ?? $payload['transaction_status']
            ?? $status->label();

        $transaction->forceFill([
            'status' => $status,
            'failure_reason' => Str::limit((string) $reason, 1000, ''),
            'gateway_status' => (string) ($payload['transaction_status'] ?? $status->value),
            'paid_at' => null,
        ])->save();

        $allocationStatus = $status === PaymentTransactionStatus::Failed
            ? PaymentAllocationStatus::Failed
            : PaymentAllocationStatus::Cancelled;

        $transaction->allocations()
            ->whereIn('status', [
                PaymentAllocationStatus::Pending->value,
                PaymentAllocationStatus::Ready->value,
                PaymentAllocationStatus::Approved->value,
            ])
            ->get()
            ->each(function (PaymentAllocation $allocation) use ($allocationStatus, $reason) {
                $before = $allocation->status;
                $allocation->update([
                    'status' => $allocationStatus,
                    'midtrans_status' => $allocationStatus->value,
                    'failed_at' => $allocationStatus === PaymentAllocationStatus::Failed ? now() : null,
                    'last_error' => Str::limit((string) $reason, 1000, ''),
                ]);

                $this->recordEvent(
                    $allocation,
                    'transaction_closed',
                    $before->value,
                    $allocationStatus->value,
                    null,
                    'Transaksi induk ditutup: '.$reason
                );
            });

        $this->restoreStockOnce($transaction, $source);
    }

    private function restoreStockOnce(PaymentTransaction $transaction, string $source): void
    {
        if ($transaction->stock_restored_at !== null) {
            return;
        }

        if (Order::where('payment_transaction_id', $transaction->id)->where('status', 'dibayar')->exists()) {
            return;
        }

        $orders = $transaction->orders()->with('items')->get();

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();

                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }
        }

        $transaction->forceFill([
            'stock_restored_at' => now(),
            'metadata' => array_merge($transaction->metadata ?? [], [
                'stock_restored_source' => $source,
            ]),
        ])->save();
    }

    private function reserveStockAgain(PaymentTransaction $transaction): void
    {
        $orders = $transaction->orders()->with('items.product')->get();

        if ($transaction->stock_restored_at === null) {
            return;
        }

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product_id
                    ? Product::whereKey($item->product_id)->lockForUpdate()->first()
                    : null;

                if (! $product || ! $product->is_active || $product->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'payment' => 'Stok produk "'.$item->product_name.'" tidak lagi mencukupi untuk pembayaran ulang.',
                    ]);
                }
            }
        }

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::whereKey($item->product_id)->decrement('stock', $item->quantity);
                }
            }
        }
    }

    /**
     * @param  Collection<int, Order>|EloquentCollection<int, Order>  $orders
     */
    private function assertOrderTotals(Collection|EloquentCollection $orders, int $grossAmount): void
    {
        $orderTotal = (int) $orders->sum(fn (Order $order) => (int) $order->total);

        if ($orderTotal !== $grossAmount) {
            throw new RuntimeException('Total transaksi gabungan tidak sama dengan jumlah total pesanan.');
        }
    }

    private function isSuccessfulStatus(string $status, string $fraudStatus): bool
    {
        return $status === 'settlement'
            || ($status === 'capture' && in_array($fraudStatus, ['', 'accept'], true));
    }

    private function isPendingStatus(string $status, string $fraudStatus): bool
    {
        return in_array($status, ['pending', 'authorize'], true)
            || ($status === 'capture' && $fraudStatus === 'challenge');
    }

    private function terminalStatus(string $status, string $fraudStatus = ''): ?PaymentTransactionStatus
    {
        if ($status === 'capture' && $fraudStatus === 'deny') {
            return PaymentTransactionStatus::Failed;
        }

        return match ($status) {
            'expire' => PaymentTransactionStatus::Expired,
            'cancel' => PaymentTransactionStatus::Cancelled,
            'deny', 'failure' => PaymentTransactionStatus::Failed,
            'refund', 'partial_refund', 'chargeback' => PaymentTransactionStatus::Refunded,
            default => null,
        };
    }

    private function nextReference(): string
    {
        do {
            $reference = 'PAY-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (PaymentTransaction::where('reference', $reference)->exists());

        return $reference;
    }

    private function midtransOrderId(PaymentTransaction $transaction, int $attemptNumber): string
    {
        $orderId = $attemptNumber === 1
            ? $transaction->reference
            : $transaction->reference.'-R'.$attemptNumber;

        return Str::limit($orderId, 50, '');
    }

    private function recordEvent(
        PaymentAllocation $allocation,
        string $action,
        ?string $statusBefore,
        ?string $statusAfter,
        ?int $actorId,
        ?string $message,
        array $payload = [],
    ): PaymentAllocationEvent {
        return $allocation->events()->create([
            'action' => $action,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'actor_id' => $actorId,
            'message' => $message,
            'payload' => $payload ?: null,
        ]);
    }
}
