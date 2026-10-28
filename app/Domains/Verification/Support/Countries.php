<?php

namespace App\Domains\Verification\Support;

/**
 * Passport-issuing countries and territories, with official English names from
 * PHP's intl data (no extra package). South Africa is left out: South Africans
 * verify with their ID number.
 */
final class Countries
{
    /** Codes in the intl data that aren't countries. */
    private const NOT_COUNTRIES = ['EU', 'EZ', 'UN', 'QO', 'XA', 'XB', 'ZZ', 'ZA'];

    /** @return array<string, string> code => name, sorted by name */
    public static function forPassports(): array
    {
        static $list = null;
        if ($list !== null) {
            return $list;
        }
        $list = [];
        $bundle = \ResourceBundle::create('en', 'ICUDATA-region');
        foreach ($bundle?->get('Countries') ?? [] as $code => $name) {
            if (is_string($code) && preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, self::NOT_COUNTRIES, true)) {
                $list[$code] = (string) $name;
            }
        }
        asort($list, SORT_LOCALE_STRING);

        return $list;
    }
}
