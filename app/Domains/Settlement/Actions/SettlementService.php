<?php

namespace App\Domains\Settlement\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\RefundService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\Refund;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Settlement\Models\SettlementDefault;
use App\Domains\Settlement\Models\SettlementStep;
use App\Domains\Settlement\Models\Suspension;
use App\Domains\Settlement\Notifications\SettlementNotice;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Support\SecureFiles;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settlement after the close (sprint 7 spec, decisions S2–S5).
 * All money moves go through the Sprint 5 ledger and refund batches.
 */
class SettlementService
{
    public function __construct(private readonly RefundService $refunds) {}

    private static function today(): CarbonImmutable
    {
        return CarbonImmutable::today((string) config('platform.display_timezone'));
    }

    /** Opens the winner's case (called by the close) with deadlines from settings. */
    public function open(Listing $listing, int $approvalId, int $price, string $kind = 'sale'): SettlementCase
    {
        $s = app(AuctionSettings::class);

        return SettlementCase::create([
            'listing_id' => $listing->id, 'bidder_approval_id' => $approvalId, 'kind' => $kind, 'price' => $price, 'status' => 'open',
            'agreement_due' => RefundService::businessDaysFrom(self::today(), $s->settlement_agreement_business_days)->toDateString(),
            'balance_due' => self::today()->addDays($s->settlement_balance_days)->toDateString(),
        ]);
    }

    public function recordStep(SettlementCase $case, string $step, string $doneOn, ?UploadedFile $document, ?string $note, User $by): void
    {
        $this->expect($case, ['open']);
        if (! array_key_exists($step, SettlementCase::STEPS)) {
            throw ValidationException::withMessages(['step' => __('Unknown step.')]);
        }

        SettlementStep::updateOrCreate(['settlement_case_id' => $case->id, 'step' => $step], [
            'done_on' => $doneOn, 'note' => $note, 'recorded_by' => $by->id,
            'document_path' => $document ? SecureFiles::put($document, "settlement/{$case->ulid}") : null,
            'document_mime' => $document?->getMimeType(),
        ]);
        Audit::record('settlement', 'step', SettlementCase::STEPS[$step]." for {$case->listing->title} ({$doneOn})", $case, $by);
    }

    /** All three steps done: the deposit is applied to the price or released, per its terms (S5). */
    public function complete(SettlementCase $case, User $by): void
    {
        $this->expect($case, ['open']);
        $missing = array_diff(array_keys(SettlementCase::STEPS), $case->steps()->pluck('step')->all());
        if ($missing !== []) {
            throw ValidationException::withMessages(['case' => __('Record every step first: :steps.', ['steps' => implode(', ', array_map(fn ($s) => SettlementCase::STEPS[$s], $missing))])]);
        }

        DB::transaction(function () use ($case, $by) {
            $deposit = $this->depositOf($case);
            if ($deposit !== null && $deposit->balance() > 0) {
                if ($case->listing->deposit_terms->value === 'applied') {
                    LedgerEntry::create(['deposit_id' => $deposit->id, 'type' => 'applied', 'amount' => -$deposit->balance(), 'source' => 'manual',
                        'source_ref' => "settlement {$case->ulid}", 'user_id' => $by->id, 'note' => 'Applied to the purchase price', 'created_at' => now()]);
                    $deposit->forceFill(['status' => 'applied'])->save();
                } else {
                    $this->refunds->schedule($deposit, 'security_released', $by, self::today());
                }
            }
            $case->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        });

        Audit::record('settlement', 'completed', "Settlement completed for {$case->listing->title}", $case, $by);
        $case->approval->user->notify(new SettlementNotice('completed', __('Settlement complete: :title', ['title' => $case->listing->title]), [
            $case->listing->deposit_terms->value === 'applied' ? __('Your deposit has been applied to the purchase price.') : __('Your security deposit will be refunded to your verified account; we will email the receipt.'),
            __('Transfer progress is shown in your account.'),
        ], route('account')));
    }

    /** One person declares a default after a missed deadline (S3); the forfeiture is capped. */
    public function declareDefault(SettlementCase $case, string $reason, int $auctionCostCents, bool $securitySignoff, User $by): SettlementDefault
    {
        $this->expect($case, ['open']);
        $missed = $case->missedDeadline() ?? throw ValidationException::withMessages(['default' => __('A default can be declared only after a missed deadline.')]);
        $deposit = $this->depositOf($case) ?? throw ValidationException::withMessages(['default' => __('No deposit is held for this buyer.')]);

        if ($case->listing->deposit_terms->value === 'security' && ! $securitySignoff) {
            throw ValidationException::withMessages(['security_signoff' => __('This is a security deposit: confirm that forfeiture is approved under the sale terms.')]);
        }
        if ($case->default()->whereIn('status', ['proposed', 'confirmed'])->exists()) {
            throw ValidationException::withMessages(['default' => __('A default is already recorded for this case.')]);
        }

        $proposed = self::cap($case->price * 100, $auctionCostCents, $deposit->balance());
        $default = SettlementDefault::updateOrCreate(['settlement_case_id' => $case->id], [
            'reason' => "{$reason} ({$missed})", 'auction_cost' => $auctionCostCents, 'proposed_forfeit' => $proposed, 'security_signoff' => $securitySignoff,
            'status' => 'proposed', 'declared_by' => $by->id, 'declared_at' => now(), 'confirmed_by' => null, 'confirmed_at' => null,
        ]);
        Audit::record('settlement', 'default_declared', "Default declared for {$case->listing->title}: forfeit R ".number_format($proposed / 100, 2, '.', ' ').' proposed', $case, $by);

        return $default;
    }

    /** The lower of 10% of the price and the actual auction cost, never more than the deposit held (PRD). */
    public static function cap(int $priceCents, int $auctionCostCents, int $heldCents): int
    {
        return max(0, min(intdiv($priceCents, 10), $auctionCostCents, $heldCents));
    }

    /** A second person confirms: forfeiture, excess refund, suspension, runner-up offer. */
    public function confirmDefault(SettlementCase $case, User $by): void
    {
        $default = $case->default()->where('status', 'proposed')->first() ?? throw ValidationException::withMessages(['default' => __('There is no default waiting for confirmation.')]);
        if ($default->declared_by === $by->id) {
            throw ValidationException::withMessages(['default' => __('A different person must confirm the default.')]);
        }

        $buyer = $case->approval->user;
        DB::transaction(function () use ($case, $default, $by, $buyer) {
            /** @var Deposit $deposit */
            $deposit = Deposit::whereKey($this->depositOf($case)?->id)->lockForUpdate()->firstOrFail();
            $forfeit = min($default->proposed_forfeit, $deposit->balance());
            LedgerEntry::create(['deposit_id' => $deposit->id, 'type' => 'forfeited', 'amount' => -$forfeit, 'source' => 'manual',
                'source_ref' => "default {$case->ulid}", 'user_id' => $by->id, 'note' => 'Forfeited after default (capped)', 'created_at' => now()]);
            $deposit->forceFill(['status' => 'forfeited'])->save();
            if ($deposit->balance() > 0) {
                $deposit->forceFill(['status' => 'received'])->save();
                $this->refunds->schedule($deposit, 'default_excess', $by, self::today());
            }

            $default->forceFill(['status' => 'confirmed', 'confirmed_by' => $by->id, 'confirmed_at' => now()])->save();
            $case->forceFill(['status' => 'defaulted'])->save();
            $case->approval->forceFill(['status' => 'revoked', 'reason' => 'Default on settlement.'])->save();
            Suspension::create(['user_id' => $buyer->id, 'until' => self::today()->addMonths(app(AuctionSettings::class)->default_suspension_months)->toDateString(),
                'reason' => "Default on {$case->listing->title}", 'created_by' => $by->id]);
        });

        Audit::record('settlement', 'default_confirmed', "Default confirmed for {$case->listing->title}", $case, $by);
        $buyer->notify(new SettlementNotice('default', __('Default on :title', ['title' => $case->listing->title]), [
            __('The settlement deadline was missed and a default has been recorded under the Rules of Auction.'),
            __('Part of your deposit has been forfeited within the legal cap; any remainder will be refunded to your verified account.'),
            __('Your bidding is suspended for :m months.', ['m' => app(AuctionSettings::class)->default_suspension_months]),
        ]));

        $this->offerRunnerUp($case->listing);
    }

    /** After a default, the runner-up is offered the property at their own highest bid (S4). */
    public function offerRunnerUp(Listing $listing): ?SettlementCase
    {
        $result = DB::table('auction_results')->where('listing_id', $listing->id)->first();
        if ($result === null || $result->runner_up_approval_id === null) {
            return null;
        }
        $approval = BidderApproval::with('user')->find((int) $result->runner_up_approval_id);
        $deposit = $approval ? Deposit::where('bidder_approval_id', $approval->id)->first() : null;
        if ($approval === null || $deposit === null || $deposit->balance() <= 0
            || SettlementCase::where('listing_id', $listing->id)->where('bidder_approval_id', $approval->id)->exists()) {
            return null; // already refunded, or already offered
        }

        $case = SettlementCase::create([
            'listing_id' => $listing->id, 'bidder_approval_id' => $approval->id, 'kind' => 'runner_up', 'price' => (int) $result->runner_up_amount,
            'status' => 'offered', 'offer_expires_at' => now()->addDays(app(AuctionSettings::class)->runner_up_accept_days),
        ]);
        Audit::record('settlement', 'runner_up_offered', "Offered {$listing->title} to the runner-up at R ".number_format((int) $result->runner_up_amount, 0, '.', ' '), $case);
        $approval->user->notify(new SettlementNotice('offer', __('Offer: :title', ['title' => $listing->title]), [
            __('The winning bidder did not complete. As the runner-up, you are offered the property at your own highest bid of R :amount.', ['amount' => number_format((int) $result->runner_up_amount, 0, '.', ' ')]),
            __('Please accept or decline by :date. If you decline or do not reply, your deposit is refunded.', ['date' => $case->offer_expires_at?->setTimezone((string) config('platform.display_timezone'))->format('j F Y, H:i')]),
        ], route('account')));

        return $case;
    }

    public function acceptOffer(SettlementCase $case, User $buyer): void
    {
        $this->assertOffer($case, $buyer);
        DB::transaction(function () use ($case) {
            $deposit = $this->depositOf($case);
            if ($deposit !== null) {
                Refund::where('deposit_id', $deposit->id)->where('status', 'scheduled')->update(['status' => 'cancelled']);
                $deposit->forceFill(['status' => 'received'])->save();
            }
            $s = app(AuctionSettings::class);
            $case->forceFill([
                'status' => 'open', 'offer_expires_at' => null,
                'agreement_due' => RefundService::businessDaysFrom(self::today(), $s->settlement_agreement_business_days)->toDateString(),
                'balance_due' => self::today()->addDays($s->settlement_balance_days)->toDateString(),
            ])->save();
        });
        Audit::record('settlement', 'runner_up_accepted', "Runner-up accepted {$case->listing->title}", $case, $buyer);
    }

    public function declineOffer(SettlementCase $case, User $buyer): void
    {
        $this->assertOffer($case, $buyer);
        $case->forceFill(['status' => 'declined'])->save();
        Audit::record('settlement', 'runner_up_declined', "Runner-up declined {$case->listing->title}", $case, $buyer);
    }

    private function assertOffer(SettlementCase $case, User $buyer): void
    {
        abort_unless($case->approval->user_id === $buyer->id, 403);
        if ($case->status !== 'offered' || ($case->offer_expires_at && $case->offer_expires_at->isPast())) {
            throw ValidationException::withMessages(['offer' => __('This offer is no longer open.')]);
        }
    }

    private function depositOf(SettlementCase $case): ?Deposit
    {
        return Deposit::where('bidder_approval_id', $case->bidder_approval_id)->first();
    }

    /** @param list<string> $statuses */
    private function expect(SettlementCase $case, array $statuses): void
    {
        if (! in_array($case->status, $statuses, true)) {
            throw ValidationException::withMessages(['case' => __('This settlement is :status.', ['status' => strtolower(SettlementCase::STATUSES[$case->status] ?? $case->status)])]);
        }
    }
}
