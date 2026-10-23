<?php

namespace App\Domains\Payments\Support;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Reads bank statement exports (CSV or OFX) into credit lines:
 * date, amount in cents, description. Debits are skipped.
 */
final class StatementParser
{
    /** @return list<array{date: string, amount: int, description: string}> */
    public static function parse(string $content, string $filename): array
    {
        $isOfx = str_ends_with(strtolower($filename), '.ofx') || str_contains(substr($content, 0, 500), 'OFXHEADER') || str_contains($content, '<STMTTRN>');

        $lines = $isOfx ? self::ofx($content) : self::csv($content);
        if ($lines === []) {
            throw new RuntimeException('No incoming payments were found in this file. Check it is a CSV or OFX statement export.');
        }

        return $lines;
    }

    /** @return list<array{date: string, amount: int, description: string}> */
    private static function ofx(string $content): array
    {
        $out = [];
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $content, $blocks);
        foreach ($blocks[1] as $block) {
            $get = fn (string $tag): string => preg_match("/<{$tag}>([^<\r\n]+)/i", $block, $m) ? trim($m[1]) : '';
            $amount = self::cents($get('TRNAMT'));
            if ($amount <= 0) {
                continue;
            }
            $date = self::date(substr($get('DTPOSTED'), 0, 8));
            if ($date === null) {
                continue;
            }
            $out[] = [
                'date' => $date,
                'amount' => $amount,
                'description' => trim($get('NAME').' '.$get('MEMO').' '.$get('FITID')),
            ];
        }

        return $out;
    }

    /** @return list<array{date: string, amount: int, description: string}> */
    private static function csv(string $content): array
    {
        $rows = array_map(fn ($l) => str_getcsv($l, self::delimiter($content), '"', '\\'), preg_split('/\R/', trim($content)) ?: []);
        $header = null;
        $out = [];

        foreach ($rows as $row) {
            $lower = array_map(fn ($c) => strtolower(trim((string) $c)), $row);
            if ($header === null) {
                if (self::col($lower, ['date']) !== null && (self::col($lower, ['amount']) !== null || self::col($lower, ['credit']) !== null)) {
                    $header = [
                        'date' => self::col($lower, ['date']),
                        'description' => self::col($lower, ['description', 'narrative', 'reference', 'details']),
                        'amount' => self::col($lower, ['amount']),
                        'credit' => self::col($lower, ['credit']),
                    ];
                }

                continue;
            }

            $amount = $header['credit'] !== null ? self::cents((string) ($row[$header['credit']] ?? '')) : self::cents((string) ($row[$header['amount']] ?? ''));
            if ($amount <= 0) {
                continue;
            }
            $date = self::date((string) ($row[$header['date']] ?? ''));
            if ($date === null) {
                continue;
            }
            $out[] = ['date' => $date, 'amount' => $amount, 'description' => trim((string) ($header['description'] !== null ? ($row[$header['description']] ?? '') : implode(' ', $row)))];
        }

        if ($header === null) {
            throw new RuntimeException('Could not find the date and amount columns. The CSV needs a header row with “Date” and “Amount” (or “Credit”).');
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $cols
     * @param  list<string>  $names
     */
    private static function col(array $cols, array $names): ?int
    {
        foreach ($cols as $i => $c) {
            foreach ($names as $n) {
                if (str_contains($c, $n)) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function delimiter(string $content): string
    {
        $first = strtok($content, "\n") ?: '';

        return substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    }

    /** "1 200 000.00", "1200000,00", "R1,200,000.50", "-50.00" → cents. */
    public static function cents(string $value): int
    {
        $v = str_replace(['R', ' ', "\u{00A0}"], '', trim($value));
        if ($v === '') {
            return 0;
        }
        if (preg_match('/,\d{2}$/', $v) && ! str_contains($v, '.')) {
            $v = str_replace(',', '.', $v);       // 1200000,00
        }
        $v = str_replace(',', '', $v);             // thousands separators

        return is_numeric($v) ? (int) round(((float) $v) * 100) : 0;
    }

    private static function date(string $value): ?string
    {
        $value = trim($value);
        foreach (['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'Ymd', 'd M Y', 'j M Y'] as $format) {
            try {
                $d = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue; // Carbon throws when the value doesn't fit the format
            }
            if ($d !== null && $d->format($format) === $value) {
                return $d->toDateString();
            }
        }

        return null;
    }
}
