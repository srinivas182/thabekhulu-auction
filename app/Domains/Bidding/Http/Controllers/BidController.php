<?php

namespace App\Domains\Bidding\Http\Controllers;

use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Actions\TenderService;
use App\Domains\Bidding\Support\StepUp;
use App\Domains\Listings\Models\Listing;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Buyer bids (6a). Large bids need a one-time code (B6). */
class BidController extends Controller
{
    public static function otpThreshold(Listing $listing): int
    {
        return StepUp::threshold($listing);
    }

    public function tender(Request $request, Listing $listing, #[CurrentUser] User $user, TenderService $tenders): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1', 'max:100000000000'], 'otp' => ['nullable', 'digits:6']]);
        StepUp::verify($listing, $user, (int) $data['amount'], $data['otp'] ?? null);
        $tenders->submit($listing, $user, (int) $data['amount']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your sealed offer is recorded. Nobody can see it until the tender is opened after the deadline.')]);

        return back();
    }

    public function store(Request $request, Listing $listing, #[CurrentUser] User $user, BidEngine $engine): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'maximum' => ['nullable', 'integer', 'min:1', 'max:100000000000'],
            'otp' => ['nullable', 'digits:6'],
        ]);
        $amount = (int) $data['amount'];
        $maximum = isset($data['maximum']) ? (int) $data['maximum'] : null;

        StepUp::verify($listing, $user, max($amount, $maximum ?? 0), $data['otp'] ?? null);

        $result = $engine->place($listing, $user, $amount, $maximum, $request->ip());
        Inertia::flash('toast', ['type' => $result['leading'] ? 'success' : 'warning', 'message' => $result['leading']
            ? __('Bid accepted. You’re the highest bidder.')
            : __('Bid accepted, but another bidder’s maximum is higher. You’ve been outbid.')]);

        return back();
    }
}
