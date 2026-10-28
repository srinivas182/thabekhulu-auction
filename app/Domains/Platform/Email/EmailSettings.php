<?php

namespace App\Domains\Platform\Email;

use Spatie\LaravelSettings\Settings;

/** Back office » Email provider. The password or API key is encrypted and never shown again. */
class EmailSettings extends Settings
{
    public bool $enabled;

    public string $provider;

    public string $host;

    public int $port;

    public string $username;

    public string $password;

    public string $from_address;

    public string $from_name;

    public static function group(): string
    {
        return 'email';
    }

    /** @return list<string> */
    public static function encrypted(): array
    {
        return ['password'];
    }

    /** Enabled and complete enough to send. */
    public function ready(): bool
    {
        return $this->enabled && $this->host !== '' && $this->username !== '' && $this->password !== '' && filter_var($this->from_address, FILTER_VALIDATE_EMAIL) !== false;
    }
}
