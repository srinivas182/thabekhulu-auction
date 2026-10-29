<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Premium\Models\PremiumSlot;
use App\Domains\Premium\Support\Badges;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function p7Listing(string $title, string $city = 'emalahleni', array $extra = []): Listing
{
    return Listing::create($extra + [
        'category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', $city)->value('id'), 'title' => $title, 'slug' => str($title)->slug().'-'.uniqid(),
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDays(30), 'ends_at' => now()->addDays(5),
    ]);
}

function p7Bidder(Listing $l, string $name, string $number): User
{
    $u = User::factory()->create(['type' => 'buyer', 'first_name' => $name, 'last_name' => 'Secret']);
    $ap = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => $number]);
    Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
    $d = Deposit::create(['listing_id' => $l->id, 'user_id' => $u->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make($number), 'status' => 'awaiting']);
    app(DepositService::class)->receive($d, 5000000, 'bank_import', "p7 {$number}");

    return $u;
}

function p7Close(Listing $l): void
{
    $l->update(['ends_at' => now()->subSecond()]);
    AuctionState::where('listing_id', $l->id)->update(['ends_at' => now()->subSecond()]);
    test()->artisan('auctions:close');
}

it('computes the badges', function () {
    $old = p7Listing('Old house');
    $new = p7Listing('Fresh house', extra: ['starts_at' => now()->subDays(2)]);
    $reno = p7Listing('Renovated house', extra: ['deal_type' => 'renovated', 'seller_price' => 700000]);
    expect(Badges::for($old))->toBe([])->and(Badges::for($new))->toContain('new')->and(Badges::for($reno))->toContain('renovated');

    BidEngine::state($old)->update(['bid_count' => 99]);
    expect(Badges::for($old))->toContain('hot');

    PremiumSlot::create(['listing_id' => $old->id, 'category_id' => $old->category_id, 'position' => 1, 'starts_on' => today()->addDay(), 'ends_on' => today()->addDays(5)]);
    expect(Badges::for($old))->not->toContain('premium');
    PremiumSlot::query()->update(['starts_on' => today()->subDay()]);
    expect(Badges::for($old))->toContain('premium');
});

it('puts premium listings first when browsing their category, within their dates and city', function () {
    $a = p7Listing('Alpha house', extra: ['ends_at' => now()->addDays(1)]);
    $b = p7Listing('Bravo house', extra: ['ends_at' => now()->addDays(9)]);
    $first = function (array $q) {
        $title = null;
        $this->get('/auctions?'.http_build_query($q))->assertOk()->assertInertia(function (Assert $p) use (&$title) {
            $title = $p->toArray()['props']['results']['data'][0]['title'];
        });

        return $title;
    };
    expect($first(['category' => 'homes']))->toBe('Alpha house'); // closes first

    PremiumSlot::create(['listing_id' => $b->id, 'category_id' => $b->category_id, 'city_id' => $b->city_id, 'position' => 1, 'starts_on' => today(), 'ends_on' => today()->addDays(3)]);
    expect($first(['category' => 'homes']))->toBe('Alpha house')                          // city-specific slot: not without the city
        ->and($first(['category' => 'homes', 'city' => 'emalahleni']))->toBe('Bravo house');

    PremiumSlot::query()->update(['city_id' => null]);
    expect($first(['category' => 'homes']))->toBe('Bravo house');
    $this->travel(5)->days();
    expect($first(['category' => 'homes']))->toBe('Alpha house');                           // slot over
});

it('refuses overlapping or invalid slots and needs premium.manage', function () {
    $a = p7Listing('Alpha');
    $b = p7Listing('Bravo');
    $draft = p7Listing('Draft', extra: ['status' => ListingStatus::Draft]);
    $slot = fn (Listing $l, array $o = []) => $this->actingAs($this->admin)->post(route('admin.premium.store'), $o + ['listing' => $l->ulid, 'position' => 1, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(7)->toDateString()]);

    $slot($a)->assertSessionHasNoErrors();
    $slot($b)->assertSessionHasErrors('position');                                   // position 1 taken
    $slot($b, ['position' => 2])->assertSessionHasNoErrors();
    $slot($a, ['position' => 3])->assertSessionHasErrors('listing');                 // same listing, same place and dates
    $slot($a, ['position' => 1, 'starts_on' => today()->addDays(8)->toDateString(), 'ends_on' => today()->addDays(9)->toDateString()])->assertSessionHasNoErrors();
    $slot($draft, ['position' => 3])->assertSessionHasErrors('listing');
    $slot($b, ['position' => 3, 'city_id' => City::where('slug', '!=', 'emalahleni')->value('id')])->assertSessionHasErrors('city_id');

    $this->actingAs(User::factory()->staff(SystemRole::KycOfficer)->create())->get(route('admin.premium.index'))->assertForbidden();
});

it('shows a sold result with price, date and bidder number, never the name', function () {
    $l = p7Listing('Sold house');
    [$a, $b] = [p7Bidder($l, 'Amahle', '1111'), p7Bidder($l, 'Bheki', '2222')];
    app(BidEngine::class)->place($l, $b, 1000000);
    app(BidEngine::class)->place($l, $a, 1250000);
    p7Close($l);

    // Under offer: the price isn't published until settlement completes.
    $this->get("/auctions/homes/emalahleni/{$l->slug}")->assertInertia(fn (Assert $p) => $p->where('auction.outcome', 'under_offer')->where('auction.result', null));
    $l->fresh()->forceFill(['status' => ListingStatus::Sold])->save(); // settlement completed

    $this->get("/auctions/homes/emalahleni/{$l->slug}")->assertInertia(function (Assert $p) {
        $p->where('auction.outcome', 'sold')->where('auction.result.price', 1250000)->where('auction.result.bidderNumber', '1111')->where('auction.result.date', now('Africa/Johannesburg')->format('j F Y'));
        expect(json_encode($p->toArray()['props']))->not->toContain('Amahle')->not->toContain('Secret');
    });
});

it('hides a no-bids listing and relists it cleanly so it can close again', function () {
    $l = p7Listing('Quiet house');
    $a = p7Bidder($l, 'Amahle', '1111');
    p7Close($l);
    expect($l->fresh()->status)->toBe(ListingStatus::NoBids);
    $this->get("/auctions/homes/emalahleni/{$l->slug}")->assertNotFound();

    $this->actingAs($this->admin)->post(route('admin.listings.relist', $l))->assertSessionHasNoErrors();
    $l->refresh();
    expect($l->status)->toBe(ListingStatus::Draft)
        ->and(DB::table('auction_results')->where('listing_id', $l->id)->exists())->toBeFalse()
        ->and(BidderApproval::where('user_id', $a->id)->value('status'))->toBe('revoked');
    $this->actingAs($this->admin)->post(route('admin.listings.relist', $l))->assertSessionHasErrors('status'); // only no-bids

    // Back on sale with new dates: it bids and closes again without clashing with the old result.
    $l->update(['status' => ListingStatus::Live, 'ends_at' => now()->addDays(3)]);
    $b = p7Bidder($l, 'Bheki', '3333');
    app(BidEngine::class)->place($l, $b, 1000000);
    p7Close($l);
    expect($l->fresh()->status)->toBe(ListingStatus::UnderOffer)->and(DB::table('auction_results')->where('listing_id', $l->id)->count())->toBe(1)
        ->and(Bid::where('listing_id', $l->id)->whereNull('voided_at')->count())->toBe(1);
});
