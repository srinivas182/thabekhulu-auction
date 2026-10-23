<?php

namespace App\Domains\Payments\Support;

use App\Domains\Payments\Models\Deposit;
use Illuminate\Support\Facades\DB;

/**
 * Daily reconciliation (spec 7): totals from the ledger, per listing, and
 * three cross-checks. "Balanced" only when every check agrees to the cent.
 */
final class Reconciliation
{
    /**
     * @return array{totals: array{received: int, refunded: int, held: int}, listings: array<int, array<string, mixed>>, issues: list<string>, balanced: bool}
     */
    public static function run(): array
    {
        $totals = [
            'received' => (int) DB::table('ledger_entries')->where('type', 'received')->sum('amount'),
            'refunded' => -(int) DB::table('ledger_entries')->where('type', 'refunded')->sum('amount'),
            'held' => (int) DB::table('ledger_entries')->sum('amount'),
        ];

        $listings = DB::table('deposits as d')->join('listings as l', 'l.id', '=', 'd.listing_id')
            ->leftJoin('ledger_entries as e', 'e.deposit_id', '=', 'd.id')
            ->groupBy('l.id', 'l.title')->orderBy('l.title')
            ->selectRaw("l.title, count(distinct d.id) as deposits,
                coalesce(sum(case when e.type = 'received' then e.amount else 0 end), 0) as received,
                coalesce(-sum(case when e.type = 'refunded' then e.amount else 0 end), 0) as refunded,
                coalesce(sum(e.amount), 0) as held")
            ->get()->map(fn ($r) => ['listing' => $r->title, 'deposits' => (int) $r->deposits, 'received' => (int) $r->received, 'refunded' => (int) $r->refunded, 'held' => (int) $r->held])->all();

        $issues = [];

        foreach (Deposit::whereIn('status', ['received', 'refunded', 'refund_scheduled', 'awaiting'])->get() as $d) {
            $balance = $d->balance();
            $problem = match ($d->status) {
                'received' => $balance !== $d->amount ? 'received but holds '.self::r($balance).' instead of '.self::r($d->amount) : null,
                'refunded' => $balance !== 0 ? 'refunded but still holds '.self::r($balance) : null,
                'refund_scheduled' => $balance <= 0 ? 'refund scheduled but nothing is held' : null,
                'awaiting' => $balance !== 0 ? 'awaiting payment but holds '.self::r($balance) : null,
                default => null,
            };
            if ($problem !== null) {
                $issues[] = "{$d->reference}: {$problem}";
            }
        }

        $bank = (int) DB::table('bank_lines')->whereIn('status', ['matched', 'resolved'])->sum('amount');
        $bankLedger = (int) DB::table('ledger_entries')->where('source', 'bank_import')->sum('amount');
        if ($bank !== $bankLedger) {
            $issues[] = 'Bank statements show '.self::r($bank).' applied to deposits, the ledger '.self::r($bankLedger).'.';
        }

        $refundsPaid = (int) DB::table('refunds')->where('status', 'paid')->sum('amount');
        if ($refundsPaid !== $totals['refunded']) {
            $issues[] = 'Paid refunds total '.self::r($refundsPaid).', the ledger '.self::r($totals['refunded']).'.';
        }

        return ['totals' => $totals, 'listings' => $listings, 'issues' => $issues, 'balanced' => $issues === []];
    }

    private static function r(int $cents): string
    {
        return 'R '.number_format($cents / 100, 2, '.', ' ');
    }
}
