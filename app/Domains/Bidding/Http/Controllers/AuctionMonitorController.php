<?php

namespace App\Domains\Bidding\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Bidding\Models\MaxBid;
use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Verification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff auction monitor (auctions.monitor): bids with names, pause, void (B9), bidders' record. */
class AuctionMonitorController extends Controller
{
    public function show(Listing $listing): Response
    {
        $state = BidEngine::state($listing);
        $tz = (string) config('platform.display_timezone');
        $names = BidderApproval::with('user:id,first_name,last_name')->where('listing_id', $listing->id)->get()->mapWithKeys(fn ($a) => [$a->id => $a->user->name]);

        return Inertia::render('admin/listings/auction', [
            'listing' => ['ulid' => $listing->ulid, 'title' => $listing->title, 'minimum' => (int) $listing->minimum_price],
            'state' => ['current' => $state->current_amount, 'leader' => $state->leader_approval_id ? $names[$state->leader_approval_id] ?? null : null,
                'endsAt' => $state->ends_at->setTimezone($tz)->format('j M Y, H:i:s'), 'extensions' => $state->extensions, 'paused' => $state->paused_at !== null, 'closed' => $state->ends_at->isPast()],
            'bids' => Bid::where('listing_id', $listing->id)->orderByDesc('id')->limit(300)->get()->map(fn (Bid $b) => [
                'id' => $b->id, 'number' => $b->bidder_number, 'name' => $names[$b->bidder_approval_id] ?? '?', 'amount' => $b->amount, 'kind' => $b->kind,
                'time' => $b->placed_at->setTimezone($tz)->format('j M H:i:s'), 'ip' => $b->ip_address, 'voided' => $b->voided_at !== null, 'voidReason' => $b->void_reason,
            ]),
            'can' => ['pause' => Gate::allows('auctions.pause'), 'void' => Gate::allows('auctions.void_bid')],
        ]);
    }

    public function pause(Listing $listing, #[CurrentUser] User $actor): RedirectResponse
    {
        DB::transaction(function () use ($listing, $actor) {
            BidEngine::state($listing);
            /** @var AuctionState $state */
            $state = AuctionState::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            if ($state->paused_at === null) {
                $state->paused_at = CarbonImmutable::now();
                Audit::record('bidding', 'paused', "Paused bidding on {$listing->title}", $listing, $actor);
            } else {
                // Give bidders back the time the auction was paused.
                $state->ends_at = $state->ends_at->addSeconds((int) $state->paused_at->diffInSeconds(CarbonImmutable::now()));
                $state->paused_at = null;
                Audit::record('bidding', 'resumed', "Resumed bidding on {$listing->title}; close moved to {$state->ends_at}", $listing, $actor);
            }
            $state->save();
            BidEngine::announce($listing, $state);
        });

        return back();
    }

    public function void(Request $request, Bid $bid, #[CurrentUser] User $actor): RedirectResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        abort_if($bid->voided_at !== null, 422);

        DB::transaction(function () use ($bid, $reason, $actor) {
            /** @var AuctionState $state */
            $state = AuctionState::whereKey($bid->listing_id)->lockForUpdate()->firstOrFail();
            $bid->forceFill(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason])->save();
            // The bidder's maximum made in error goes too; the auction continues from the last valid bid.
            MaxBid::where('listing_id', $bid->listing_id)->where('bidder_approval_id', $bid->bidder_approval_id)->update(['voided_at' => now()]);
            $last = Bid::where('listing_id', $bid->listing_id)->whereNull('voided_at')->orderByDesc('id')->first();
            $state->forceFill(['current_amount' => $last?->amount, 'leader_approval_id' => $last?->bidder_approval_id])->save();
            BidEngine::announce(Listing::findOrFail($bid->listing_id), $state);
        });

        Audit::record('bidding', 'bid_voided', "Voided bid #{$bid->bidder_number} R ".number_format($bid->amount, 0, '.', ' ').": {$reason}", null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bid voided; the auction continues from the previous valid bid.')]);

        return back();
    }

    /** Bidders' record (CPA regulation 26) for inspection. */
    public function biddersRecord(Listing $listing, #[CurrentUser] User $actor): StreamedResponse
    {
        Audit::record('bidding', 'bidders_record_exported', "Exported the bidders' record for {$listing->title}", $listing, $actor);

        return response()->streamDownload(function () use ($listing) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Bidder number', 'Full name', 'ID or passport', 'Bidding for', 'Registration number', 'Email', 'Mobile', 'Approved'], ',', '"', '\\');
            foreach (BidderApproval::with(['user', 'entity'])->where('listing_id', $listing->id)->where('status', 'approved')->orderBy('bidder_number')->get() as $a) {
                $v = Verification::where('user_id', $a->user_id)->where('status', 'verified')->latest('id')->first();
                fputcsv($out, [$a->bidder_number, $a->user->name, $v?->id_number, $a->entity->name ?? 'Self', $a->entity?->registration_number, $a->user->email, $a->user->mobile, $a->decided_at?->toDateString()], ',', '"', '\\');
            }
            fclose($out);
        }, "bidders-record-{$listing->slug}.csv", ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, private']);
    }
}
