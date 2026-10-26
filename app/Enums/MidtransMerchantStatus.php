<?php

namespace App\Enums;

enum MidtransMerchantStatus: string
{
    case Unregistered = 'unregistered';
    case Pending = 'pending';
    case Registered = 'registered';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Unregistered => 'Belum terdaftar',
            self::Pending => 'Menunggu verifikasi',
            self::Registered => 'Terdaftar',
            self::Suspended => 'Ditangguhkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unregistered => 'badge-soft red',
            self::Pending => 'badge-soft amber',
            self::Registered => 'badge-soft green',
            self::Suspended => 'badge-soft red',
        };
    }

    public function canReceiveSplit(): bool
    {
        return $this === self::Registered;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
