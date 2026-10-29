<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Settlement\Actions\SettlementPayments;
use App\Domains\Settlement\Models\SettlementPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Confirm received" by hand: one finance user records a payment seen in the
 * bank, a different one approves it (two-person, like refunds). Only then is
 * the deposit or balance updated.
 */
final class PaymentConfirmations
{
    public function __construct(private readonly DepositService $deposits, private readonly SettlementPayments $balances) {}

    public function request(string $kind, int $targetId, int $cents, string $bankReference, string $paidOn, User $by, ?string $note = null): int
    {
        $this->target($kind, $targetId); // exists and still owes money
        if ($cents <= 0) {
            throw ValidationException::withMessages(['amount' => __('Enter the amount received.')]);
        }
        $id = DB::table('payment_confirmations')->insertGetId(['kind' => $kind, 'target_id' => $targetId, 'amount' => $cents, 'bank_reference' => $bankReference,
            'paid_on' => $paidOn, 'status' => 'pending', 'requested_by' => $by->id, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('payments', 'confirmation_requested', ucfirst($kind).' payment of R '.number_format($cents / 100, 2, '.', ' ')." recorded for approval ({$bankReference})", null, $by);

        return $id;
    }

    public function approve(int $id, User $by): void
    {
        DB::transaction(function () use ($id, $by) {
            $c = DB::table('payment_confirmations')->lockForUpdate()->where('id', $id)->where('status', 'pending')->first() ?? throw ValidationException::withMessages(['confirmation' => __('This confirmation was already decided.')]);
            if ((int) $c->requested_by === $by->id) {
                throw ValidationException::withMessages(['confirmation' => __('A different person must approve this: payments need two people.')]);
            }
            $target = $this->target((string) $c->kind, (int) $c->target_id);
            $source = "manual ({$c->bank_reference}, paid {$c->paid_on})";
            $target instanceof Deposit
                ? $this->deposits->receive($target, (int) $c->amount, 'manual', $source, $by)
                : $this->balances->receive($target, (int) $c->amount, $source, $by);
            DB::table('payment_confirmations')->where('id', $id)->update(['status' => 'approved', 'decided_by' => $by->id, 'updated_at' => now()]);
            Audit::record('payments', 'confirmation_approved', "Approved payment confirmation #{$id}", null, $by);
        });
    }

    public function reject(int $id, User $by, string $note): void
    {
        $n = DB::table('payment_confirmations')->where('id', $id)->where('status', 'pending')->update(['status' => 'rejected', 'decided_by' => $by->id, 'note' => $note, 'updated_at' => now()]);
        if ($n === 0) {
            throw ValidationException::withMessages(['confirmation' => __('This confirmation was already decided.')]);
        }
        Audit::record('payments', 'confirmation_rejected', "Rejected payment confirmation #{$id}: {$note}", null, $by);
    }

    private function target(string $kind, int $id): Deposit|SettlementPayment
    {
        $t = match ($kind) {
            'deposit' => Deposit::find($id),
            'balance' => SettlementPayment::find($id),
            default => null,
        };
        $owing = $t instanceof Deposit ? in_array($t->status, ['awaiting', 'part_paid'], true) : ($t instanceof SettlementPayment && $t->status !== 'received');
        if ($t === null || ! $owing) {
            throw ValidationException::withMessages(['confirmation' => __('There’s nothing outstanding on this payment.')]);
        }

        return $t;
    }
}
