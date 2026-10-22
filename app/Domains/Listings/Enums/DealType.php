<?php

namespace App\Domains\Listings\Enums;

enum DealType: string
{
    case Commission = 'commission';
    case Renovated = 'renovated';

    public function label(): string
    {
        return match ($this) {
            self::Commission => 'Commission sale',
            self::Renovated => 'Thabekhulu-renovated',
        };
    }
}
