<?php

namespace App\Models;

use App\Enums\MidtransMerchantStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo',
        'phone',
        'address',
        'city_id',
        'city_name',
        'province_name',
        'is_active',
        'midtrans_merchant_id',
        'midtrans_merchant_status',
        'payout_bank_code',
        'payout_bank_name',
        'payout_account_number',
        'payout_account_name',
        'payout_account_verified_at',
        'payout_account_verified_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'midtrans_merchant_status' => MidtransMerchantStatus::class,
            'payout_account_number' => 'encrypted',
            'payout_account_verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Store $store) {
            $owner = User::find($store->user_id);

            if (! $owner?->isSeller()) {
                throw new LogicException('Toko hanya dapat dimiliki oleh akun penjual.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isReadyForSplit(): bool
    {
        return filled($this->midtrans_merchant_id)
            && $this->midtrans_merchant_status?->canReceiveSplit();
    }

    public function hasVerifiedPayoutAccount(): bool
    {
        return filled($this->payout_bank_code)
            && filled($this->payout_account_number)
            && filled($this->payout_account_name)
            && $this->payout_account_verified_at !== null;
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

    public function getInitialsAttribute(): string
    {
        return Str::of($this->name)->explode(' ')->take(2)->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))->implode('');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        if (Str::startsWith($this->logo, 'images/')) {
            return asset($this->logo);
        }

        return Storage::disk('public')->url($this->logo);
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
