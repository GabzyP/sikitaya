<?php

namespace App\Models;

use App\Enums\PaymentAllocationMethod;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'checkout_token',
        'user_id',
        'provider',
        'allocation_method',
        'status',
        'currency',
        'gross_amount',
        'platform_commission',
        'midtrans_fee',
        'fee_bearer',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'snap_token',
        'snap_redirect_url',
        'payment_type',
        'fraud_status',
        'gateway_status',
        'paid_at',
        'expires_at',
        'notified_at',
        'notification_count',
        'failure_reason',
        'gateway_payload',
        'metadata',
        'stock_restored_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentTransactionStatus::class,
            'allocation_method' => PaymentAllocationMethod::class,
            'gross_amount' => 'integer',
            'platform_commission' => 'integer',
            'midtrans_fee' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'notified_at' => 'datetime',
            'notification_count' => 'integer',
            'gateway_payload' => 'array',
            'metadata' => 'array',
            'stock_restored_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function latestAttempt(): HasOne
    {
        return $this->hasOne(PaymentAttempt::class)->latestOfMany();
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PaymentWebhookLog::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentTransactionStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentTransactionStatus::Pending;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function canRetry(): bool
    {
        return $this->status->canRetry() && ! $this->orders()->where('status', 'dibayar')->exists();
    }

    public function paymentUrl(): ?string
    {
        return $this->snap_redirect_url;
    }

    public function feeNote(): string
    {
        return $this->midtrans_fee > 0
            ? 'Biaya Midtrans ditanggung platform sebesar '.rupiah($this->midtrans_fee).'.'
            : 'Biaya Midtrans ditanggung platform dan tidak mengurangi alokasi penjual.';
    }
}
