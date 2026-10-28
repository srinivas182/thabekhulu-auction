<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Settings\PaymentSettings;
use App\Domains\Payments\Support\PaymentRegistry;
use App\Domains\Payments\Support\ReceiptPdf;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Support\SecureFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'reference' => $deposit->reference, 'status' => $deposit->status, 'statusLabel' => Deposit::STATUSES[$deposit->status], 'proofUploadedAt' => $deposit->proof_uploaded_at?->setTimezone((string) config('platform.display_timezone'))->format('j F Y, H:i'),
                'amount' => $deposit->amount, 'paid' => $deposit->balance(),
                'receipts' => $deposit->receipts->map(fn (Receipt $r) => ['number' => $r->number, 'amount' => $r->amount, 'url' => route('receipts.download', $r->number)]),
            ],
            'escrow' => ($p = app(PaymentRegistry::class)->forDeposits()) ? ['key' => $p->key(), 'name' => $p->name()] : null,
            'returned' => request()->query('status'),
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

    /** Sends the buyer to the escrow provider's payment page (link valid briefly, never stored). */
    public function pay(Listing $listing, #[CurrentUser] User $user, PaymentRegistry $registry): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($user->isBuyer(), 403);
        $provider = $registry->forDeposits() ?? abort(404);
        $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->firstOrFail();
        $deposit = Deposit::where('bidder_approval_id', $approval->id)->firstOrFail();
        abort_if(in_array($deposit->status, ['received', 'refund_scheduled', 'refunded', 'applied'], true), 422);

        try {
            $url = $provider->checkoutUrl($deposit, $registry->credentials($provider->key()), $registry->setting($provider->key())->mode, route('deposits.show', $listing->ulid));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['pay' => __(':name is unavailable right now. You can still pay by EFT using the details below.', ['name' => $provider->name()])]);
        }
        $deposit->save();

        return Inertia::location($url);
    }

    public function receipt(string $number, #[CurrentUser] User $user): HttpResponse
    {
        $receipt = Receipt::with('deposit')->where('number', $number)->firstOrFail();
        abort_unless($receipt->deposit->user_id === $user->id || $user->can('deposits.view'), 403);

        return response(ReceiptPdf::render($receipt), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$receipt->number.'.pdf"', 'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * The buyer's proof of an EFT payment. It helps finance find the payment;
     * the deposit is confirmed only when the money reflects in the bank.
     */
    public function proof(Request $request, Listing $listing, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($user->isBuyer(), 403);
        $request->validate(['proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->where('status', 'approved')->firstOrFail();
        $deposit = Deposit::where('bidder_approval_id', $approval->id)->whereIn('status', ['awaiting', 'part_paid'])->firstOrFail();

        $file = $request->file('proof');
        SecureFiles::delete($deposit->proof_path);
        $deposit->forceFill(['proof_path' => SecureFiles::put($file, 'deposit-proofs'), 'proof_mime' => (string) $file->getMimeType(), 'proof_uploaded_at' => now()])->save();
        Audit::record('payments', 'deposit_proof_uploaded', "Proof of payment uploaded for {$deposit->reference}", $deposit, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. Your deposit is confirmed once the payment reflects in our account, usually within one working day.')]);

        return back();
    }

    /** Finance opens a buyer's proof of payment (deposits.view); every opening is logged. */
    public function showProof(Deposit $deposit, #[CurrentUser] User $actor): \Symfony\Component\HttpFoundation\Response
    {
        abort_if($deposit->proof_path === null, 404);
        Audit::record('payments', 'deposit_proof_viewed', "Opened proof of payment for {$deposit->reference}", $deposit, $actor);

        return response(SecureFiles::read((string) $deposit->proof_path), 200, [
            'Content-Type' => (string) $deposit->proof_mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
