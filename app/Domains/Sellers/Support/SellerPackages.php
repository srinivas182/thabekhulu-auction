<?php

namespace App\Domains\Sellers\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** PRD: a seller's auction may not end after their package expires. */
final class SellerPackages
{
    /** The latest expiry among the seller's packages that have started, or null if none. */
    public static function expiryFor(int $userId): ?CarbonImmutable
    {
        $date = DB::table('seller_package_assignments')->where('user_id', $userId)->whereDate('starts_on', '<=', CarbonImmutable::today())->max('expires_on');

        return $date ? CarbonImmutable::parse((string) $date)->endOfDay() : null;
    }

    /** Null if allowed, otherwise the reason. */
    public static function endProblem(int $sellerId, ?CarbonInterface $endsAt): ?string
    {
        if ($endsAt === null) {
            return null;
        }
        $expiry = self::expiryFor($sellerId);
        if ($expiry === null) {
            return (string) __('The seller has no active package.');
        }

        return $endsAt->greaterThan($expiry) ? (string) __('The auction may not end after the seller’s package expires on :date.', ['date' => $expiry->format('j F Y')]) : null;
    }
}
