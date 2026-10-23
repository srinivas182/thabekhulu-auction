<?php

namespace App\Domains\Verification\Support;

use Illuminate\Support\Carbon;

/**
 * South African ID numbers: YYMMDD SSSS C A Z, 13 digits, Luhn check digit.
 */
final class SaIdNumber
{
    public static function isValid(string $number): bool
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return strlen($digits) === 13 && self::dateOfBirth($digits) !== null && in_array($digits[10], ['0', '1'], true) && self::luhn($digits);
    }

    /** Date of birth from the first six digits; the century is inferred (not in the future). */
    public static function dateOfBirth(string $number): ?Carbon
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';
        if (strlen($digits) < 6) {
            return null;
        }

        [$yy, $mm, $dd] = [(int) substr($digits, 0, 2), (int) substr($digits, 2, 2), (int) substr($digits, 4, 2)];
        $year = 2000 + $yy > (int) now()->format('Y') ? 1900 + $yy : 2000 + $yy;

        return checkdate($mm, $dd, $year) ? Carbon::create($year, $mm, $dd) : null;
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
