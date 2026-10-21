<?php

namespace App\Domains\Identity\Enums;

enum UserType: string
{
    case Staff = 'staff';
    case Buyer = 'buyer';
    case Seller = 'seller';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Buyer => 'Buyer',
            self::Seller => 'Seller',
        };
    }
}
