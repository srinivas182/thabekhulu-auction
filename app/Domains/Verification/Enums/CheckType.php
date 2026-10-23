<?php

namespace App\Domains\Verification\Enums;

enum CheckType: string
{
    case SaIdentity = 'sa_identity';
    case PassportIdentity = 'passport_identity';
    case Liveness = 'liveness';
    case Company = 'company';
    case Screening = 'screening';
    case BankAccount = 'bank_account';

    public function label(): string
    {
        return match ($this) {
            self::SaIdentity => 'South African ID (Home Affairs)',
            self::PassportIdentity => 'Passport (non-SA)',
            self::Liveness => 'Selfie and liveness',
            self::Company => 'Company (CIPC)',
            self::Screening => 'Sanctions and PEP screening',
            self::BankAccount => 'Bank account in own name',
        };
    }
}
