<?php

namespace App\Domains\Audit\Support;

use Illuminate\Support\Collection;

/**
 * Removes secrets and masks personal details before they reach the audit
 * log. Passwords, tokens and 2FA secrets are never stored; mobile numbers
 * keep only the last four digits.
 */
final class Masker
{
    private const HIDDEN_KEYS = ['password', 'secret', 'token', 'recovery_codes', 'remember_token', 'two_factor_secret'];

    private const PARTIAL_KEYS = ['mobile', 'phone', 'contact_phone'];

    /**
     * @param  Collection<array-key, mixed>|array<array-key, mixed>|null  $properties
     * @return Collection<array-key, mixed>
     */
    public static function mask(Collection|array|null $properties): Collection
    {
        return collect(self::walk(collect($properties ?? [])->all()));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function walk(array $data): array
    {
        foreach ($data as $key => $value) {
            $name = strtolower((string) $key);

            if (self::matches($name, self::HIDDEN_KEYS)) {
                $data[$key] = '[hidden]';
            } elseif (self::matches($name, self::PARTIAL_KEYS) && is_string($value) && $value !== '') {
                $data[$key] = str_repeat('•', max(strlen($value) - 4, 0)).substr($value, -4);
            } elseif (is_array($value)) {
                $data[$key] = self::walk($value);
            }
        }

        return $data;
    }

    /**
     * @param  list<string>  $needles
     */
    private static function matches(string $name, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }
}
