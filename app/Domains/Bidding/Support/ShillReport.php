<?php

namespace App\Domains\Bidding\Support;

use App\Domains\Bidding\Models\Bid;
use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\BiddingEntity;

/**
 * Patterns for staff review (sprint 6 spec 7). Flags, never decisions:
 * a person reviews each one before acting.
 */
final class ShillReport
{
    /** @return array<int, array{kind: string, detail: string, bidders: array<int, string>}> */
    public static function for(Listing $listing): array
    {
        $approvals = BidderApproval::with(['user:id,first_name,last_name', 'entity'])->where('listing_id', $listing->id)->where('status', 'approved')->get()->keyBy('id');
        $label = fn (int $id) => '#'.($approvals[$id]->bidder_number ?? '?').' '.($approvals[$id]->user->name ?? '');
        $bids = Bid::where('listing_id', $listing->id)->whereNull('voided_at')->orderBy('id')->get();
        $flags = [];

        // 1. Different bidders from the same IP address.
        foreach ($bids->whereNotNull('ip_address')->groupBy('ip_address') as $ip => $group) {
            $who = $group->pluck('bidder_approval_id')->unique()->values();
            if ($who->count() > 1) {
                $flags[] = ['kind' => 'Shared IP address', 'detail' => "Bids from {$ip}", 'bidders' => $who->map($label)->all()];
            }
        }

        // 2. The same refund bank account (numbers are encrypted, so compared after decryption).
        $accounts = BankAccount::whereIn('user_id', $approvals->pluck('user_id'))->get()->groupBy(fn (BankAccount $b) => $b->branch_code.'|'.$b->account_number);
        foreach ($accounts as $group) {
            if ($group->count() > 1) {
                $ids = $approvals->whereIn('user_id', $group->pluck('user_id'))->keys();
                $flags[] = ['kind' => 'Shared bank account', 'detail' => 'Refund account ending '.($group->first()->account_last4 ?? '????'), 'bidders' => $ids->map($label)->values()->all()];
            }
        }

        // 3. Companies or trusts with a controller in common.
        $byId = [];
        foreach ($approvals->filter(fn ($a) => $a->entity instanceof BiddingEntity) as $a) {
            foreach ($a->entity->controllers ?? [] as $c) {
                $byId[preg_replace('/\s/', '', (string) $c['id_number'])][] = $a->id;
            }
        }
        foreach ($byId as $ids) {
            if (count(array_unique($ids)) > 1) {
                $flags[] = ['kind' => 'Shared controller', 'detail' => 'The same person controls more than one bidding entity', 'bidders' => array_map($label, array_values(array_unique($ids)))];
            }
        }

        // 4. "Pumping": 3+ bids, each immediately answered by the same other bidder, and never the final leader.
        $leader = $bids->last()?->bidder_approval_id;
        $byBidder = $bids->groupBy('bidder_approval_id');
        foreach ($byBidder as $id => $own) {
            if ($own->count() < 3 || (int) $id === $leader) {
                continue;
            }
            $answers = $own->map(fn (Bid $b) => $bids->firstWhere(fn (Bid $n) => $n->id > $b->id)?->bidder_approval_id)->unique();
            if ($answers->count() === 1 && $answers->first() !== null && $answers->first() !== (int) $id) {
                $flags[] = ['kind' => 'Possible bid pumping', 'detail' => 'Every bid was immediately answered by the same bidder, and this bidder never won', 'bidders' => [$label((int) $id), $label((int) $answers->first())]];
            }
        }

        return $flags;
    }
}
