<?php

namespace App\Domains\Settlement\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\BalanceReference;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Settlement\Models\SettlementPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The winner pays the balance to Thabekhulu; finance confirms it (statement
 * import or two-person manual confirmation); when it's paid in full, the
 * "Balance paid" step is recorded automatically.
 */
final class SettlementPayments
{
    public function __construct(private readonly SettlementService $settlement) {}

    /** The balance record for a case: price less the deposit where the deposit counts towards the price. */
    public function forCase(SettlementCase $case): SettlementPayment
    {
        $existing = SettlementPayment::where('settlement_case_id', $case->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return SettlementPayment::create([
            'settlement_case_id' => $case->id,
            'reference' => BalanceReference::make((string) $case->approval->bidder_number),
            'amount_due' => self::breakdown($case)['due'],
        ]);
    }

    /** @return array{price: int, deposit: int, due: int} cents */
    public static function breakdown(SettlementCase $case): array
    {
        $price = (int) $case->price * 100;
        $deposit = 0;
        if ($case->listing->deposit_terms->value === 'applied') {
            $d = Deposit::where('bidder_approval_id', $case->bidder_approval_id)->whereIn('status', ['received', 'applied'])->first();
            $deposit = $d === null ? 0 : ($d->status === 'applied' ? (int) $d->amount : $d->balance());
        }

        return ['price' => $price, 'deposit' => $deposit, 'due' => max(0, $price - $deposit)];
    }

    /** Money in against the balance. Records "Balance paid" once it's paid in full. */
    public function receive(SettlementPayment $payment, int $cents, string $source, User $by): void
    {
        DB::transaction(function () use ($payment, $cents, $source, $by) {
            $p = SettlementPayment::lockForUpdate()->findOrFail($payment->id);
            $p->received += $cents;
            $paid = $p->received >= $p->amount_due;
            $p->status = $paid ? 'received' : 'part_paid';
            $p->received_at = $paid ? now() : null;
            $p->save();
            Audit::record('settlement', 'balance_received', 'Balance payment of R '.number_format($cents / 100, 2, '.', ' ')." for {$p->reference} ({$source})", $p->case, $by);

            $case = $p->case;
            if ($paid && $case->status === 'open' && ! $case->steps()->where('step', 'balance_paid')->exists()) {
                $this->settlement->recordStep($case, 'balance_paid', now()->toDateString(), null, "Balance received by Thabekhulu ({$p->reference})", $by);
            }
        });
    }
}
