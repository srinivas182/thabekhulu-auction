<?php

namespace App\Domains\Listings\Enums;

enum AuctionType: string
{
    case Open = 'open';
    case Sealed = 'sealed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open auction',
            self::Sealed => 'Sealed tender',
        };
    }
}
