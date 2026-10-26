<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'store_id',
        'recipient_name',
        'phone',
        'address',
        'city_id',
        'city_name',
        'province_name',
        'postal_code',
        'courier',
        'shipping_service',
        'shipping_etd',
        'shipping_cost',
        'subtotal',
        'total',
        'payment_method',
        'payment_transaction_id',
        'status',
        'payment_proof',
        'payment_proof_uploaded_at',
        'paid_at',
        'shipped_at',
        'completed_at',
        'tracking_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'shipping_cost' => 'integer',
            'subtotal' => 'integer',
            'total' => 'integer',
            'payment_proof_uploaded_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function paymentAllocation(): HasOne
    {
        return $this->hasOne(PaymentAllocation::class);
    }

    public function isMidtransPayment(): bool
    {
        return $this->payment_method === PaymentMethod::Midtrans;
    }

    public function hasPendingOnlinePayment(): bool
    {
        return $this->isMidtransPayment()
            && $this->status === OrderStatus::Menunggu
            && $this->paymentTransaction?->isPending();
    }

    public function paymentStatusLabel(): string
    {
        if (! $this->isMidtransPayment()) {
            return $this->status->label();
        }

        return $this->paymentTransaction?->status->label() ?? 'Menunggu pembayaran';
    }

    public function scopeForBuyer(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function getTotalWeightAttribute(): int
    {
        return $this->items->sum(fn (OrderItem $item) => $item->weight * $item->quantity);
    }

    public function requiresPaymentProof(): bool
    {
        return $this->payment_method->requiresProof();
    }

    public function canBePaidByBuyer(): bool
    {
        return $this->status === OrderStatus::Menunggu && $this->requiresPaymentProof();
    }

    public function paymentProofUrl(): ?string
    {
        return $this->payment_proof ? asset('storage/'.$this->payment_proof) : null;
    }

    public function isProofPdf(): bool
    {
        return $this->payment_proof && Str::lower(pathinfo($this->payment_proof, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function courierLabel(): string
    {
        return strtoupper($this->courier).' '.$this->shipping_service;
    }

    public function statusStepIndex(): int
    {
        return array_search($this->status, OrderStatus::flow(), true) ?: 0;
    }
}
