<?php

namespace App\Domains\Payments\Settings;

use Spatie\LaravelSettings\Settings;

class PaymentSettings extends Settings
{
    public string $trust_bank;

    public string $trust_account_name;

    public string $trust_account_number;

    public string $trust_branch_code;

    public int $refund_business_days;

    public int $runner_up_hold_days;

    public static function group(): string
    {
        return 'payments';
    }

    public function trustAccountReady(): bool
    {
        return $this->trust_bank !== '' && $this->trust_account_number !== '' && $this->trust_branch_code !== '';
    }
}
