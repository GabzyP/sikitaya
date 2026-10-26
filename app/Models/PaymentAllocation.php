<?php

namespace App\Models;

use App\Enums\PaymentAllocationMethod;
use App\Enums\PaymentAllocationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_transaction_id',
        'order_id',
        'store_id',
        'seller_id',
        'method',
        'status',
        'gross_amount',
        'platform_commission',
        'midtrans_fee',
        'net_amount',
        'merchant_id',
        'payout_bank_code',
        'payout_bank_name',
        'payout_account_number',
        'payout_account_name',
        'midtrans_reference',
        'midtrans_status',
        'attempt_count',
        'disbursed_amount',
        'requested_by',
        'approved_by',
        'requested_at',
        'approved_at',
        'processed_at',
        'completed_at',
        'failed_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentAllocationMethod::class,
            'status' => PaymentAllocationStatus::class,
            'gross_amount' => 'integer',
            'platform_commission' => 'integer',
            'midtrans_fee' => 'integer',
            'net_amount' => 'integer',
            'attempt_count' => 'integer',
            'disbursed_amount' => 'integer',
            'payout_account_number' => 'encrypted',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentAllocationEvent::class);
    }

    public function isSplit(): bool
    {
        return $this->method === PaymentAllocationMethod::Split;
    }

    public function isDisbursement(): bool
    {
        return $this->method === PaymentAllocationMethod::Disbursement;
    }

    public function canBeApproved(): bool
    {
        return $this->isDisbursement()
            && $this->status->canApprove()
            && $this->transaction?->isPaid();
    }

    public function canBeSent(): bool
    {
        return $this->isDisbursement()
            && $this->status->canSend()
            && $this->transaction?->isPaid()
            && $this->net_amount > $this->disbursed_amount;
    }

    public function maskedPayoutAccountNumber(): string
    {
        $number = (string) ($this->payout_account_number ?? '');

        if ($number === '') {
            return '-';
        }

        if (Str::length($number) <= 4) {
            return str_repeat('•', Str::length($number));
        }

        return str_repeat('•', Str::length($number) - 4).Str::substr($number, -4);
    }

    public function remainingAmount(): int
    {
        return max(0, $this->net_amount - $this->disbursed_amount);
    }
}
