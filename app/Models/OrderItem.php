<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_image',
        'product_price',
        'quantity',
        'subtotal',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'product_price' => 'integer',
            'quantity' => 'integer',
            'subtotal' => 'integer',
            'weight' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->product_image) {
            return asset('images/products/placeholder.svg');
        }

        if (str_starts_with($this->product_image, 'images/')) {
            return asset($this->product_image);
        }

        return asset('storage/'.$this->product_image);
    }
}
