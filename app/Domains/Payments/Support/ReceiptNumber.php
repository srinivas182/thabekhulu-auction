<?php

namespace App\Domains\Payments\Support;

use Illuminate\Support\Facades\DB;

/** Gap-free receipt numbers per year: TDG-R-2026-0001 (call inside a transaction). */
final class ReceiptNumber
{
    public static function next(?int $year = null): string
    {
        $year ??= (int) now((string) config('platform.display_timezone'))->format('Y');

        $row = DB::table('receipt_sequences')->where('year', $year)->lockForUpdate()->first();
        if ($row === null) {
            DB::table('receipt_sequences')->insert(['year' => $year, 'last_number' => 1]);
            $number = 1;
        } else {
            $number = (int) $row->last_number + 1;
            DB::table('receipt_sequences')->where('year', $year)->update(['last_number' => $number]);
        }

        return sprintf('TDG-R-%d-%04d', $year, $number);
    }
}
