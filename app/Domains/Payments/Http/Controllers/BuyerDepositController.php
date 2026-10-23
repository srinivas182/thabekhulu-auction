<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Settings\PaymentSettings;
use App\Domains\Payments\Support\ReceiptPdf;
use App\Domains\Verification\Models\BidderApproval;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** The buyer's deposit for one listing: payment details, status and receipts. */
class BuyerDepositController extends Controller
{
    public function show(Listing $listing, #[CurrentUser] User $user): Response
    {
        abort_unless($user->isBuyer(), 403);
        $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->first();
        $deposit = $approval ? Deposit::with('receipts')->where('bidder_approval_id', $approval->id)->first() : null;
        $settings = app(PaymentSettings::class);

        return Inertia::render('account/deposit', [
            'listing' => ['ulid' => $listing->ulid, 'title' => $listing->title, 'url' => PublicListing::url($listing), 'amount' => (int) $listing->deposit_amount, 'terms' => $listing->deposit_terms->value],
            'approval' => $approval?->status,
            'gateOpen' => DepositService::complianceComplete($listing),
            'deposit' => $deposit === null ? null : [
                'reference' => $deposit->reference, 'status' => $deposit->status, 'statusLabel' => Deposit::STATUSES[$deposit->status],
                'amount' => $deposit->amount, 'paid' => $deposit->balance(),
                'receipts' => $deposit->receipts->map(fn (Receipt $r) => ['number' => $r->number, 'amount' => $r->amount, 'url' => route('receipts.download', $r->number)]),
            ],
            'bank' => $settings->trustAccountReady() ? [
                'bank' => $settings->trust_bank, 'name' => $settings->trust_account_name, 'number' => $settings->trust_account_number, 'branch' => $settings->trust_branch_code,
            ] : null,
        ]);
    }

    public function open(Listing $listing, #[CurrentUser] User $user, DepositService $deposits): RedirectResponse
    {
        abort_unless($user->isBuyer(), 403);
        $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->firstOrFail();
        $deposits->open($approval, $user);

        return back();
    }

    public function receipt(string $number, #[CurrentUser] User $user): HttpResponse
    {
        $receipt = Receipt::with('deposit')->where('number', $number)->firstOrFail();
        abort_unless($receipt->deposit->user_id === $user->id || $user->can('deposits.view'), 403);

        return response(ReceiptPdf::render($receipt), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$receipt->number.'.pdf"', 'Cache-Control' => 'no-store, private',
        ]);
    }
}
