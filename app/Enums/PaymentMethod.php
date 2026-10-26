<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Midtrans = 'midtrans';
    case Cod = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer Bank',
            self::Qris => 'QRIS',
            self::Midtrans => 'Midtrans Online',
            self::Cod => 'COD (Bayar di Tempat)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
            self::Midtrans => 'Midtrans',
            self::Cod => 'COD',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Transfer => 'bi-bank',
            self::Qris => 'bi-qr-code',
            self::Midtrans => 'bi-credit-card-2-front',
            self::Cod => 'bi-cash-coin',
        };
    }

    /**
     * Transfer/QRIS simulasi memerlukan unggah bukti manual.
     * Midtrans diverifikasi oleh gateway, COD dibayar saat diterima.
     */
    public function requiresProof(): bool
    {
        return in_array($this, [self::Transfer, self::Qris], true);
    }

    public function isOnline(): bool
    {
        return $this === self::Midtrans;
    }

    /**
     * Metode checkout default: simulasi transfer/QRIS dan COD. Midtrans hanya
     * ditambahkan jika integrasi checkout-nya diaktifkan secara eksplisit.
     *
     * @return list<self>
     */
    public static function checkoutOptions(bool $includeMidtrans = false): array
    {
        $methods = [self::Transfer, self::Qris];

        if ($includeMidtrans) {
            $methods[] = self::Midtrans;
        }

        $methods[] = self::Cod;

        return $methods;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method) => [$method->value => $method->label()])
            ->all();
    }
}
