<?php

namespace App\Domains\Bidding\Http\Controllers;

use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Notifications\BidCode;
use App\Domains\Listings\Models\Listing;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Buyer bids (6a). Large bids need a one-time code (B6). */
class BidController extends Controller
{
    public static function otpThreshold(Listing $listing): int
    {
        return min(app(AuctionSettings::class)->step_up_threshold, 2 * (int) $listing->minimum_price);
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

        if (max($amount, $maximum ?? 0) >= self::otpThreshold($listing)) {
            $this->stepUp($listing, $user, max($amount, $maximum ?? 0), $data['otp'] ?? null);
        }

        $result = $engine->place($listing, $user, $amount, $maximum, $request->ip());
        Inertia::flash('toast', ['type' => $result['leading'] ? 'success' : 'warning', 'message' => $result['leading']
            ? __('Bid accepted. You’re the highest bidder.')
            : __('Bid accepted, but another bidder’s maximum is higher. You’ve been outbid.')]);

        return back();
    }

    private function stepUp(Listing $listing, User $user, int $amount, ?string $otp): void
    {
        $row = DB::table('bid_otps')->where('user_id', $user->id)->where('listing_id', $listing->id)->where('expires_at', '>', now())->latest('id')->first();

        if ($otp === null) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            DB::table('bid_otps')->where('user_id', $user->id)->where('listing_id', $listing->id)->delete();
            DB::table('bid_otps')->insert(['user_id' => $user->id, 'listing_id' => $listing->id, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10), 'created_at' => now()]);
            $user->notify(new BidCode($code, $listing->title, $amount));

            throw ValidationException::withMessages(['otp' => __('For your security, we’ve emailed you a 6-digit code. Enter it to confirm this bid.')]);
        }

        if ($row === null || $row->attempts >= 5) {
            throw ValidationException::withMessages(['otp' => __('That code has expired. Submit again to get a new one.')]);
        }
        if (! Hash::check($otp, (string) $row->code_hash)) {
            DB::table('bid_otps')->where('id', $row->id)->increment('attempts');
            throw ValidationException::withMessages(['otp' => __('That code isn’t right. Check the email and try again.')]);
        }
        DB::table('bid_otps')->where('id', $row->id)->delete(); // single use
    }
}
