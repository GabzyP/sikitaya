<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Cod = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer Bank',
            self::Qris => 'QRIS',
            self::Cod => 'COD (Bayar di Tempat)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
            self::Cod => 'COD',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Transfer => 'bi-bank',
            self::Qris => 'bi-qr-code',
            self::Cod => 'bi-cash-coin',
        };
    }

    public function requiresProof(): bool
    {
        return $this !== self::Cod;
    }

    public static function options(): array
    {
        return [
            self::Transfer->value => self::Transfer->label(),
            self::Qris->value => self::Qris->label(),
            self::Cod->value => self::Cod->label(),
        ];
    }
}
