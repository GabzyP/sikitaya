<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Paid => 'Lunas',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
            self::Refunded => 'Dikembalikan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Midtrans belum mengonfirmasi pembayaran.',
            self::Paid => 'Pembayaran berhasil dikonfirmasi oleh Midtrans.',
            self::Failed => 'Pembayaran ditolak atau gagal diproses.',
            self::Expired => 'Batas waktu pembayaran telah berakhir.',
            self::Cancelled => 'Transaksi pembayaran dibatalkan.',
            self::Refunded => 'Pembayaran telah dikembalikan.',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'status-badge status-pending',
            self::Paid => 'status-badge status-paid',
            self::Failed, self::Cancelled => 'status-badge status-failed',
            self::Expired => 'status-badge status-expired',
            self::Refunded => 'status-badge status-refunded',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /** Status akhir yang tidak boleh berubah menjadi lunas tanpa transaksi baru. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Failed, self::Expired, self::Cancelled, self::Refunded], true);
    }

    public function canRetry(): bool
    {
        return in_array($this, [self::Pending, self::Failed, self::Expired, self::Cancelled], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
