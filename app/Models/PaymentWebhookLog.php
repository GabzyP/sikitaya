<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_transaction_id',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'transaction_status',
        'gross_amount',
        'currency',
        'signature_valid',
        'processed',
        'message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'processed' => 'boolean',
            'payload' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
