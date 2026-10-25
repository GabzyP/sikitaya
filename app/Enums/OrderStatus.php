<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Menunggu = 'menunggu';
    case Dibayar = 'dibayar';
    case Dikirim = 'dikirim';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Dibayar => 'Dibayar',
            self::Dikirim => 'Dikirim',
            self::Selesai => 'Selesai',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu pembayaran atau konfirmasi penjual.',
            self::Dibayar => 'Pembayaran telah diverifikasi atau pesanan dikonfirmasi.',
            self::Dikirim => 'Pesanan sedang dalam pengiriman.',
            self::Selesai => 'Pesanan telah diterima oleh pembeli.',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Menunggu => 'status-badge status-menunggu',
            self::Dibayar => 'status-badge status-dibayar',
            self::Dikirim => 'status-badge status-dikirim',
            self::Selesai => 'status-badge status-selesai',
        };
    }

    /** @return list<self> */
    public static function flow(): array
    {
        return [self::Menunggu, self::Dibayar, self::Dikirim, self::Selesai];
    }
}
