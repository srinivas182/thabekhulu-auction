<?php

namespace App\Domains\DataRoom\Http\Controllers;

use App\Domains\Listings\Public\PublicListing;
use App\Domains\Quotes\Models\Quote;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Verification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Buyer dashboard (approved design): what needs attention, applications, account status. */
class BuyerDashboardController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $verification = Verification::where('user_id', $user->id)->latest('id')->first();
        $bank = BankAccount::where('user_id', $user->id)->latest('id')->first();
        $approvals = BidderApproval::with(['listing.category', 'listing.city'])->where('user_id', $user->id)->latest()->get();
        $tz = (string) config('platform.display_timezone');

        $attention = [];
        if ($verification === null || in_array($verification->status, ['in_progress', 'rejected'], true)) {
            $attention[] = ['kind' => 'verify', 'text' => $verification?->info_requested ? 'We need a little more for your verification: '.$verification->info_requested : 'Verify your identity to apply to bid.', 'url' => '/account/verification', 'action' => 'Verify now'];
        }
        if ($verification?->status === 'verified' && ($bank === null || $bank->status === 'rejected')) {
            $attention[] = ['kind' => 'bank', 'text' => 'Add the bank account we refund deposits to.', 'url' => '/account/bank', 'action' => 'Add account'];
        }
        if ($verification?->status === 'verified' && $verification->expires_at !== null && $verification->expires_at->lt(now()->addDays(30))) {
            $attention[] = ['kind' => 'expiry', 'text' => 'Your verification expires on '.$verification->expires_at->format('j F Y').'. Renew it to keep bidding.', 'url' => '/account/verification', 'action' => 'Renew'];
        }
        foreach ($approvals->where('status', 'approved') as $a) {
            $ndaNeeded = $a->listing->category->documentTypes()->where('visibility', 'approved_nda')->exists();
            if ($ndaNeeded && ! DB::table('nda_acceptances')->where('listing_id', $a->listing_id)->where('user_id', $user->id)->exists()) {
                $attention[] = ['kind' => 'nda', 'text' => "Sign the NDA to open the data room for {$a->listing->title}.", 'url' => route('dataroom.show', $a->listing->ulid, false), 'action' => 'Open data room'];
            }
        }

        return Inertia::render('account/dashboard', [
            'name' => $user->first_name,
            'attention' => $attention,
            'stats' => [
                'applications' => $approvals->whereIn('status', ['pending', 'approved'])->count(),
                'approved' => $approvals->where('status', 'approved')->count(),
                'quotes' => Quote::whereIn('status', ['sent', 'viewed'])->whereHas('client', fn ($c) => $c->where('user_id', $user->id)->orWhere('email', strtolower($user->email)))->count(),
            ],
            'status' => [
                'verification' => $verification === null ? 'Not started' : (Verification::STATUSES[$verification->status] ?? $verification->status),
                'verified' => $verification?->status === 'verified',
                'bank' => $bank === null ? 'Not added' : "{$bank->bank} ending {$bank->account_last4} (".($bank->status === 'verified' ? 'verified' : ($bank->status === 'pending' ? 'being checked' : 'not accepted')).')',
            ],
            'auctions' => $approvals->map(fn (BidderApproval $a): array => [
                'title' => $a->listing->title, 'url' => PublicListing::url($a->listing), 'dataRoom' => route('dataroom.show', $a->listing->ulid, false),
                'status' => BidderApproval::STATUSES[$a->status], 'approved' => $a->status === 'approved', 'bidderNumber' => $a->bidder_number,
                'closes' => $a->listing->ends_at?->setTimezone($tz)->format('j M, H:i'),
            ])->values(),
        ]);
    }
}
