<?php

namespace App\Enums;

enum PaymentAllocationStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Ready => 'Siap dicairkan',
            self::Approved => 'Disetujui admin',
            self::Processing => 'Diproses Midtrans',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Alokasi dibuat dan menunggu pembayaran gabungan lunas.',
            self::Ready => 'Pembayaran lunas. Alokasi siap disetujui admin.',
            self::Approved => 'Admin telah menyetujui alokasi untuk dikirim.',
            self::Processing => 'Pencairan telah dikirim ke Midtrans dan menunggu konfirmasi.',
            self::Completed => 'Midtrans mengonfirmasi alokasi telah selesai.',
            self::Failed => 'Pencairan gagal dan dapat ditinjau kembali.',
            self::Cancelled => 'Transaksi induk gagal, kedaluwarsa, atau dibatalkan.',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'status-badge status-pending',
            self::Ready => 'status-badge status-ready',
            self::Approved => 'status-badge status-approved',
            self::Processing => 'status-badge status-processing',
            self::Completed => 'status-badge status-completed',
            self::Failed => 'status-badge status-failed',
            self::Cancelled => 'status-badge status-expired',
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function canApprove(): bool
    {
        return $this === self::Ready;
    }

    public function canSend(): bool
    {
        return in_array($this, [self::Approved, self::Failed], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
