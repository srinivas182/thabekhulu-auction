<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Models\Refund;
use App\Domains\Payments\Models\RefundBatch;
use App\Domains\Payments\Notifications\RefundPaid;
use App\Domains\Payments\Settings\PaymentSettings;
use App\Domains\Payments\Support\PaymentRegistry;
use App\Domains\Payments\Support\ReceiptNumber;
use App\Domains\Verification\Models\BankAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Refunds (decisions P5, P6): scheduled per deposit, paid in batches that
 * one finance user prepares and a different one approves. The refund is
 * always the deposit's ledger balance; EFT refunds go only to the bidder's
 * verified, locked account; escrowed deposits are refunded by their provider.
 */
class RefundService
{
    public function __construct(private readonly PaymentRegistry $registry) {}

    /** South African business days (weekends skipped; public holidays are a known gap, see ADR 0014). */
    public static function businessDaysFrom(CarbonInterface $from, int $days): CarbonImmutable
    {
        // Dates are immutable in this app: every step must be reassigned.
        $d = $from->toImmutable()->startOfDay();
        while ($days > 0) {
            $d = $d->addDay();
            if (! $d->isWeekend()) {
                $days--;
            }
        }

        return $d;
    }

    public function schedule(Deposit $deposit, string $reason, User $by, ?CarbonInterface $dueOn = null): Refund
    {
        if ($deposit->status !== 'received' || $deposit->balance() <= 0) {
            throw ValidationException::withMessages(['refund' => __('Only a received deposit with money held can be refunded.')]);
        }
        if (Refund::where('deposit_id', $deposit->id)->whereIn('status', ['scheduled', 'batched'])->exists()) {
            throw ValidationException::withMessages(['refund' => __('A refund is already scheduled for this deposit.')]);
        }

        $dueOn ??= self::businessDaysFrom(now((string) config('platform.display_timezone')), app(PaymentSettings::class)->refund_business_days);
        $refund = Refund::create(['deposit_id' => $deposit->id, 'amount' => 0, 'reason' => $reason, 'status' => 'scheduled', 'due_on' => $dueOn->toDateString(), 'scheduled_by' => $by->id]);
        $deposit->forceFill(['status' => 'refund_scheduled'])->save();

        Audit::record('payments', 'refund_scheduled', "Refund of {$deposit->reference} scheduled for {$dueOn->toDateString()} (".Refund::REASONS[$reason].')', $deposit, $by);

        return $refund;
    }

    /**
     * Puts every due, scheduled refund that passes the checks into a new batch.
     *
     * @return array{batch: RefundBatch|null, held: list<string>}
     */
    public function prepare(User $by): array
    {
        return DB::transaction(function () use ($by) {
            $due = Refund::with('deposit')->where('status', 'scheduled')->whereDate('due_on', '<=', now((string) config('platform.display_timezone'))->toDateString())->lockForUpdate()->get();
            $ready = [];
            $held = [];

            foreach ($due as $refund) {
                $deposit = $refund->deposit;
                $balance = $deposit->balance();
                $channel = in_array($deposit->method, ['tradesafe', 'paystack'], true) && $deposit->provider_ref ? $deposit->method : 'eft';
                $problem = match (true) {
                    $balance <= 0 => 'nothing is held',
                    $channel === 'eft' && ! BankAccount::where('user_id', $deposit->user_id)->where('status', 'verified')->where('locked', true)->exists() => 'the refund account is not verified and locked',
                    $channel !== 'eft' && ! $this->registry->usable($channel, $channel === 'tradesafe' ? 'deposit' : 'small') => "{$channel} is not enabled",
                    default => null,
                };

                if ($problem !== null) {
                    $refund->forceFill(['failure' => "Held back: {$problem}."])->save();
                    $held[] = "{$deposit->reference}: {$problem}";

                    continue;
                }
                $refund->forceFill(['amount' => $balance, 'channel' => $channel, 'failure' => null])->save();
                $ready[] = $refund;
            }

            if ($ready === []) {
                return ['batch' => null, 'held' => $held];
            }

            $batch = RefundBatch::create([
                'number' => $this->batchNumber(), 'status' => 'prepared', 'total' => array_sum(array_map(fn (Refund $r) => $r->amount, $ready)),
                'count' => count($ready), 'prepared_by' => $by->id, 'prepared_at' => now(),
            ]);
            Refund::whereIn('id', array_map(fn (Refund $r) => $r->id, $ready))->update(['refund_batch_id' => $batch->id, 'status' => 'batched']);

            Audit::record('payments', 'refund_batch_prepared', "Prepared {$batch->number}: {$batch->count} refunds", $batch, $by, ['total' => $batch->total]);

            return ['batch' => $batch, 'held' => $held];
        });
    }

    /** A different finance user approves; provider-held deposits are refunded now, EFT ones wait for the bank payment. */
    public function approve(RefundBatch $batch, User $by): RefundBatch
    {
        if ($batch->status !== 'prepared') {
            throw ValidationException::withMessages(['batch' => __('This batch is not waiting for approval.')]);
        }
        if ($batch->prepared_by === $by->id) {
            throw ValidationException::withMessages(['batch' => __('A batch must be approved by someone other than the person who prepared it.')]);
        }

        $batch->forceFill(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()])->save();
        Audit::record('payments', 'refund_batch_approved', "Approved {$batch->number}", $batch, $by);

        foreach ($batch->refunds()->with('deposit')->where('channel', '!=', 'eft')->get() as $refund) {
            $provider = $this->registry->find((string) $refund->channel);
            try {
                $ref = $provider?->refund($refund->deposit, $this->registry->credentials((string) $refund->channel), $this->registry->setting((string) $refund->channel)->mode, "Auction deposit refund {$batch->number}")
                    ?? throw new \RuntimeException('Provider missing.');
                $this->markPaid($refund, $by, $ref);
            } catch (Throwable $e) {
                // Take it out of this batch and back to "scheduled" so the next batch retries it.
                $refund->forceFill(['status' => 'scheduled', 'refund_batch_id' => null, 'failure' => 'Provider refund failed: '.mb_substr($e->getMessage(), 0, 450)])->save();
                $batch->forceFill(['total' => $batch->total - $refund->amount, 'count' => $batch->count - 1, 'note' => trim(($batch->note ?? '')." {$refund->deposit->reference} removed: provider refund failed.")])->save();
                Audit::record('payments', 'refund_failed', "Refund of {$refund->deposit->reference} failed at {$refund->channel}", $refund->deposit, $by, ['error' => $e->getMessage()]);
            }
        }

        return $this->closeIfDone($batch);
    }

    /** Finance records the bank payment of the EFT refunds, with proof. */
    public function recordBankPayment(RefundBatch $batch, string $proofPath, User $by): RefundBatch
    {
        if ($batch->status !== 'approved') {
            throw ValidationException::withMessages(['batch' => __('Approve the batch before recording its payment.')]);
        }

        foreach ($batch->refunds()->with('deposit')->where('channel', 'eft')->where('status', 'batched')->get() as $refund) {
            $this->markPaid($refund, $by, "{$batch->number} bank payment");
        }
        $batch->forceFill(['proof_path' => $proofPath, 'paid_by' => $by->id])->save();

        return $this->closeIfDone($batch);
    }

    private function markPaid(Refund $refund, User $by, string $providerRef): void
    {
        DB::transaction(function () use ($refund, $by, $providerRef) {
            /** @var Deposit $deposit */
            $deposit = Deposit::whereKey($refund->deposit_id)->lockForUpdate()->firstOrFail();

            LedgerEntry::create([
                'deposit_id' => $deposit->id, 'type' => 'refunded', 'amount' => -$refund->amount, 'source' => $refund->channel === 'eft' ? 'manual' : 'provider',
                'source_ref' => $providerRef, 'user_id' => $by->id, 'note' => Refund::REASONS[$refund->reason], 'created_at' => now(),
            ]);
            $refund->forceFill(['status' => 'paid', 'paid_at' => now(), 'provider_ref' => $providerRef, 'failure' => null])->save();
            if ($deposit->balance() === 0) {
                $deposit->forceFill(['status' => 'refunded'])->save();
            }

            $receipt = Receipt::create(['number' => ReceiptNumber::next(), 'deposit_id' => $deposit->id, 'kind' => 'refund', 'amount' => $refund->amount, 'issued_at' => now()]);

            // Unlock only when no other deposit of this bidder is still held.
            $stillHeld = Deposit::where('user_id', $deposit->user_id)->whereIn('status', ['part_paid', 'received', 'refund_scheduled'])->exists();
            if (! $stillHeld) {
                BankAccount::where('user_id', $deposit->user_id)->update(['locked' => false]);
            }

            Audit::record('payments', 'refund_paid', 'Refunded R '.number_format($refund->amount / 100, 2, '.', ' ')." for {$deposit->reference}; receipt {$receipt->number}", $deposit, $by);
            DB::afterCommit(fn () => $deposit->user->notify(new RefundPaid($deposit, $receipt)));
        });
    }

    private function closeIfDone(RefundBatch $batch): RefundBatch
    {
        $open = $batch->refunds()->where('status', 'batched')->count();
        if ($open === 0 && $batch->status === 'approved') {
            // Every refund failed and went back to "scheduled": nothing was paid from this batch.
            $batch->forceFill($batch->refunds()->where('status', 'paid')->exists() ? ['status' => 'paid', 'paid_at' => now()] : ['status' => 'cancelled'])->save();
        }

        return $batch->refresh();
    }

    private function batchNumber(): string
    {
        $year = (int) now((string) config('platform.display_timezone'))->format('Y');
        $count = RefundBatch::whereYear('created_at', $year)->lockForUpdate()->count();

        return sprintf('TDG-RB-%d-%04d', $year, $count + 1);
    }
}
