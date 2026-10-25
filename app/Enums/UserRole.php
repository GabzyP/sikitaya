<?php

namespace App\Enums;

enum UserRole: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Pembeli',
            self::Seller => 'Penjual',
            self::Admin => 'Admin',
        };
    }
}
