<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Enums\ComplianceItem;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Notifications\DepositReceived;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Payments\Support\ReceiptNumber;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deposits: the compliance gate (P4), creation with a unique reference (P3),
 * recording money into the append-only ledger (P2), and confirmation:
 * receipt, buyer email and refund-account lock (P5).
 */
class DepositService
{
    /** Every compliance item on the listing is signed off. */
    public static function complianceComplete(Listing $listing): bool
    {
        $signed = $listing->complianceItems()->distinct()->pluck('item')->all();

        return collect(ComplianceItem::cases())->every(fn (ComplianceItem $i) => in_array($i->value, $signed, true));
    }

    public function open(BidderApproval $approval, User $buyer): Deposit
    {
        $listing = $approval->listing;

        if ($approval->user_id !== $buyer->id || ! in_array($approval->status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages(['deposit' => __('Apply to bid on this listing first.')]);
        }
        if (! self::complianceComplete($listing)) {
            throw ValidationException::withMessages(['deposit' => __('Deposits for this listing open once our legal checks are complete. We’ll email you.')]);
        }
        if ((int) $listing->deposit_amount <= 0) {
            throw ValidationException::withMessages(['deposit' => __('This listing has no deposit.')]);
        }

        return Deposit::firstOrCreate(['bidder_approval_id' => $approval->id], [
            'listing_id' => $listing->id, 'user_id' => $buyer->id, 'amount' => (int) $listing->deposit_amount * 100,
            'reference' => $this->uniqueReference((string) ($approval->bidder_number ?? '0')), 'method' => 'eft', 'status' => 'awaiting',
        ]);
    }

    /**
     * Records money received and confirms the deposit once fully paid.
     * Row-locked so two imports can never confirm the same deposit twice.
     */
    public function receive(Deposit $deposit, int $cents, string $source, string $sourceRef, ?User $by = null, ?string $note = null): Deposit
    {
        return DB::transaction(function () use ($deposit, $cents, $source, $sourceRef, $by, $note) {
            /** @var Deposit $locked */
            $locked = Deposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            LedgerEntry::create([
                'deposit_id' => $locked->id, 'type' => 'received', 'amount' => $cents, 'source' => $source,
                'source_ref' => $sourceRef, 'user_id' => $by?->id, 'note' => $note, 'created_at' => now(),
            ]);

            $balance = $locked->balance();
            $wasReceived = $locked->status === 'received';
            $locked->status = $balance >= $locked->amount ? 'received' : 'part_paid';

            if ($locked->status === 'received' && ! $wasReceived) {
                $locked->received_at = Carbon::now();
                $locked->save();
                $this->confirm($locked, $balance, $by);
            } else {
                $locked->save();
            }

            Audit::record('payments', 'received', 'Received R '.number_format($cents / 100, 2, '.', ' ')." for {$locked->reference}", $locked, $by, ['source' => $source, 'ref' => $sourceRef]);

            return $locked;
        });
    }

    private function confirm(Deposit $deposit, int $balance, ?User $by): void
    {
        $receipt = Receipt::create([
            'number' => ReceiptNumber::next(), 'deposit_id' => $deposit->id, 'kind' => 'deposit', 'amount' => $balance, 'issued_at' => now(),
        ]);

        // Refunds may only go to the verified account; it stays locked until the refund is paid (5c).
        BankAccount::where('user_id', $deposit->user_id)->update(['locked' => true]);

        Audit::record('payments', 'deposit_confirmed', "Deposit {$deposit->reference} confirmed; receipt {$receipt->number}", $deposit, $by);
        $deposit->user->notify(new DepositReceived($deposit, $receipt));
    }

    private function uniqueReference(string $bidderNumber): string
    {
        do {
            $reference = DepositReference::make($bidderNumber);
        } while (Deposit::where('reference', $reference)->exists());

        return $reference;
    }
}
