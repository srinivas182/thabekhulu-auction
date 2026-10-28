<?php

namespace App\Domains\Verification\Support;

use App\Domains\DataRoom\Settings\DataRoomSettings;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Domains\Verification\Http\Controllers\KycReviewController;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The per-listing approval checklist (sprint 4 spec 6, decision K6).
 * One place decides; staff and buyer screens both show its result.
 * Items from later sprints are listed as "later" and do not block yet.
 */
final class BidderEligibility
{
    public function needsProofOfFunds(Listing $listing): bool
    {
        $listing->loadMissing('category');

        return $listing->category->slug === 'mining' || (int) $listing->minimum_price >= app(AuctionSettings::class)->proof_of_funds_threshold;
    }

    /**
     * @return list<array{key: string, label: string, state: 'ok'|'missing'|'later', detail: string|null}>
     */
    public function checklist(BidderApproval $approval): array
    {
        $approval->loadMissing(['listing.category', 'entity', 'proofOfFunds']);
        $userId = $approval->user_id;
        $items = [];

        $v = Verification::where('user_id', $userId)->latest('id')->first();
        $verified = $v !== null && $v->status === 'verified' && ($v->expires_at === null || $v->expires_at->isFuture());
        $items[] = $this->item('identity', 'Identity verified', $verified, $verified ? 'Valid until '.$v->expires_at?->format('j M Y') : 'Not verified');

        $screen = Screening::where('user_id', $userId)->latest('screened_at')->first();
        $screened = $screen !== null && $screen->result === 'clear' && $screen->screened_at->gte(now()->subDays(KycReviewController::SCREENING_DAYS));
        $items[] = $this->item('screening', 'Clear sanctions and PEP screening (last 30 days)', $screened, $screen ? Screening::RESULTS[$screen->result].', '.$screen->screened_at->format('j M Y') : 'Not screened');

        $bank = BankAccount::where('user_id', $userId)->latest('id')->first();
        $items[] = $this->item('bank', 'Refund bank account verified', $bank?->status === 'verified', $bank ? "{$bank->bank} ending {$bank->account_last4} ({$bank->status})" : 'Not added');

        if ($approval->bidding_entity_id !== null) {
            $entity = $approval->entity;
            $items[] = $this->item('entity', 'Company or trust verified', $entity?->status === 'verified', $entity ? "{$entity->name} ({$entity->status})" : 'Missing');
        }

        if ($this->needsProofOfFunds($approval->listing)) {
            $minimum = (int) $approval->listing->minimum_price;
            $pof = $approval->proofOfFunds;
            $items[] = $this->item('funds', 'Proof of funds accepted and valid', $pof !== null && $pof->isValidFor($minimum),
                $pof ? 'R '.number_format($pof->amount, 0, '.', ' ')." ({$pof->status}".($pof->valid_until ? ', valid until '.$pof->valid_until->format('j M Y') : '').')' : 'Not uploaded');
        }

        // The deposit isn't part of approval: buyers pay once approved, and bidding unlocks when it's received (BidEligibility).
        if ($approval->listing->category->documentTypes()->where('visibility', 'approved_nda')->exists()) {
            $version = app(DataRoomSettings::class)->nda_version;
            $signed = DB::table('nda_acceptances')->where('listing_id', $approval->listing_id)->where('user_id', $userId)->where('nda_version', $version)->value('accepted_at');
            $items[] = $this->item('nda', 'Data-room NDA signed', $signed !== null, $signed !== null ? 'Version '.$version.', '.Carbon::parse((string) $signed)->format('j M Y') : 'Not signed');
        }

        return $items;
    }

    /** @param list<array{state: string}> $checklist */
    public static function passes(array $checklist): bool
    {
        return collect($checklist)->every(fn (array $i) => $i['state'] !== 'missing');
    }

    /** @return array{key: string, label: string, state: 'ok'|'missing', detail: string|null} */
    private function item(string $key, string $label, bool $ok, ?string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'state' => $ok ? 'ok' : 'missing', 'detail' => $detail];
    }
}
