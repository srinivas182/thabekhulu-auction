<?php

namespace App\Domains\Bidding\Support;

use App\Domains\Bidding\Notifications\BidCode;
use App\Domains\Listings\Models\Listing;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Step-up code for large bids and offers (B6): emailed, hashed, 10 minutes, 5 attempts, single use. */
final class StepUp
{
    public static function threshold(Listing $listing): int
    {
        return min(app(AuctionSettings::class)->step_up_threshold, 2 * (int) $listing->minimum_price);
    }

    public static function verify(Listing $listing, User $user, int $amount, ?string $otp): void
    {
        if ($amount < self::threshold($listing)) {
            return;
        }
        $row = DB::table('bid_otps')->where('user_id', $user->id)->where('listing_id', $listing->id)->where('expires_at', '>', now())->latest('id')->first();

        if ($otp === null) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            DB::table('bid_otps')->where('user_id', $user->id)->where('listing_id', $listing->id)->delete();
            DB::table('bid_otps')->insert(['user_id' => $user->id, 'listing_id' => $listing->id, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10), 'created_at' => now()]);
            $user->notify(new BidCode($code, $listing->title, $amount));

            throw ValidationException::withMessages(['otp' => __('For your security, we’ve emailed you a 6-digit code. Enter it to confirm.')]);
        }
        if ($row === null || $row->attempts >= 5) {
            throw ValidationException::withMessages(['otp' => __('That code has expired. Submit again to get a new one.')]);
        }
        if (! Hash::check($otp, (string) $row->code_hash)) {
            DB::table('bid_otps')->where('id', $row->id)->increment('attempts');
            throw ValidationException::withMessages(['otp' => __('That code isn’t right. Check the email and try again.')]);
        }
        DB::table('bid_otps')->where('id', $row->id)->delete();
    }
}
