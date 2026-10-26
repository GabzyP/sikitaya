<?php

namespace App\Enums;

enum PaymentAllocationMethod: string
{
    case Split = 'split';
    case Disbursement = 'disbursement';

    public function label(): string
    {
        return match ($this) {
            self::Split => 'Transaction split',
            self::Disbursement => 'Disbursement API',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Split => 'Split otomatis',
            self::Disbursement => 'Pencairan admin',
        };
    }
}
