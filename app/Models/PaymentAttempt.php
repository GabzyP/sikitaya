<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_transaction_id',
        'attempt_number',
        'midtrans_order_id',
        'status',
        'snap_token',
        'snap_redirect_url',
        'gateway_status',
        'response',
        'error',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'response' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['created', 'pending'], true)
            && filled($this->snap_redirect_url)
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
