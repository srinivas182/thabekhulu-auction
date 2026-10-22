<?php

namespace App\Domains\Quotes\Support;

use App\Domains\Quotes\Settings\QuotationSettings;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, sequential numbers per year (decision Q5): TDG-Q-2026-0001.
 * The year's row is locked so two staff creating quotes at once can never
 * receive the same number.
 */
final class QuoteNumber
{
    public static function next(): string
    {
        $year = (int) now((string) config('platform.display_timezone'))->format('Y');

        $number = DB::transaction(function () use ($year): int {
            $row = DB::table('quote_sequences')->where('year', $year)->lockForUpdate()->first();

            if ($row === null) {
                DB::table('quote_sequences')->insert(['year' => $year, 'last_number' => 1]);

                return 1;
            }

            $next = (int) $row->last_number + 1;
            DB::table('quote_sequences')->where('year', $year)->update(['last_number' => $next]);

            return $next;
        });

        return sprintf('%s-%d-%04d', app(QuotationSettings::class)->number_prefix, $year, $number);
    }
}
