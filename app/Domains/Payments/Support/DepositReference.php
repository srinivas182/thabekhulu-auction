<?php

namespace App\Domains\Payments\Support;

/**
 * Deposit references like TDG-D-4821-7Q3KX (decision P3): bidder number,
 * four random characters and a check character, from an alphabet without
 * look-alikes (no I, O, 0, 1). A mistyped reference fails the check
 * instead of matching someone else's deposit.
 */
final class DepositReference
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function make(string $bidderNumber): string
    {
        $body = '';
        for ($i = 0; $i < 4; $i++) {
            $body .= self::ALPHABET[random_int(0, 31)];
        }
        $number = str_pad(preg_replace('/\D/', '', $bidderNumber) ?: '0', 4, '0', STR_PAD_LEFT);

        return "TDG-D-{$number}-{$body}".self::check("TDGD{$number}{$body}");
    }

    public static function isValid(string $reference): bool
    {
        $plain = self::plain($reference);

        return (bool) preg_match('/^TDGD\d{4}['.self::ALPHABET.']{5}$/', $plain) && self::check(substr($plain, 0, -1)) === substr($plain, -1);
    }

    /**
     * Finds a valid reference anywhere in a bank statement description,
     * ignoring spaces, dashes and case ("tdg d 4821 7q3kx" works).
     */
    public static function find(string $text): ?string
    {
        $plain = self::plain($text);
        if (! preg_match_all('/TDGD(\d{4})(['.self::ALPHABET.']{5})/', $plain, $matches, PREG_SET_ORDER)) {
            return null;
        }
        foreach ($matches as $m) {
            $candidate = "TDG-D-{$m[1]}-{$m[2]}";
            if (self::isValid($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function plain(string $text): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
    }

    private static function check(string $plain): string
    {
        $sum = 0;
        foreach (str_split($plain) as $i => $char) {
            $value = strpos(self::ALPHABET, $char);
            // Odd weights are invertible mod 32, so every single-character typo changes the check.
            $sum += (($value === false ? ord($char) : $value) * (2 * $i + 1));
        }

        return self::ALPHABET[$sum % 32];
    }
}
