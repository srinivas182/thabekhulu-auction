<?php

namespace App\Domains\Quotes\Support;

/**
 * Quotation arithmetic in whole cents (decision Q4). Quantity is in
 * hundredths (150 = 1.5). VAT is calculated and rounded per line, the way
 * South African tax invoices are usually prepared.
 */
final class QuoteMath
{
    /**
     * @return array{gross: int, discount: int, net: int, vat: int}
     */
    public static function line(int $quantity, int $unitPrice, ?string $discountType, int $discountValue, bool $vat, int $vatRateBps): array
    {
        $gross = intdiv($quantity * $unitPrice + 50, 100); // round half up
        $discount = match ($discountType) {
            'percent' => intdiv($gross * min($discountValue, 10000) + 5000, 10000),
            'amount' => min($discountValue, $gross),
            default => 0,
        };
        $net = $gross - $discount;
        $vatAmount = $vat ? intdiv($net * $vatRateBps + 5000, 10000) : 0;

        return ['gross' => $gross, 'discount' => $discount, 'net' => $net, 'vat' => $vatAmount];
    }

    /**
     * Totals for a set of calculated lines. Once-off and recurring are kept apart.
     *
     * @param  list<array{frequency: string, kind: string, gross: int, discount: int, net: int, vat: int}>  $lines
     * @return array{once_subtotal: int, once_discount: int, once_vat: int, once_total: int, monthly_total: int, annual_total: int}
     */
    public static function totals(array $lines): array
    {
        $t = ['once_subtotal' => 0, 'once_discount' => 0, 'once_vat' => 0, 'once_total' => 0, 'monthly_total' => 0, 'annual_total' => 0];

        foreach ($lines as $l) {
            if ($l['kind'] !== 'item') {
                continue;
            }
            match ($l['frequency']) {
                'monthly' => $t['monthly_total'] += $l['net'] + $l['vat'],
                'annual' => $t['annual_total'] += $l['net'] + $l['vat'],
                default => (function () use (&$t, $l): void {
                    $t['once_subtotal'] += $l['gross'];
                    $t['once_discount'] += $l['discount'];
                    $t['once_vat'] += $l['vat'];
                    $t['once_total'] += $l['net'] + $l['vat'];
                })(),
            };
        }

        return $t;
    }

    /** Parse "1 250 000.50" / "R1250000,5" into cents. */
    public static function cents(mixed $value): int
    {
        $clean = str_replace([' ', 'R', 'r', ','], ['', '', '', '.'], (string) $value);

        return is_numeric($clean) ? (int) round(((float) $clean) * 100) : 0;
    }
}
