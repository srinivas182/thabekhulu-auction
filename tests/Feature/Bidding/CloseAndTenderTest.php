<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Actions\TenderService;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Notifications\AuctionResult;
use App\Domains\Bidding\Support\ShillReport;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\Refund;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
});

function closeListing(string $type = 'open', string $slug = 'lot'): Listing
{
    static $n = 0;
    $n++;

    return Listing::create([
        'category_id' => Category::where('slug', $type === 'sealed' ? 'mining' : 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'),
        'title' => "Lot {$n}", 'slug' => "{$slug}-{$n}", 'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000,
        'deposit_amount' => 50000, 'deposit_terms' => 'security', 'auction_type' => $type, 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addHour(),
    ]);
}

function paidBidder(Listing $l, string $name, string $number, ?string $account = null): User
{
    $u = User::factory()->create(['type' => 'buyer', 'first_name' => $name, 'last_name' => 'Test']);
    $ap = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => $number]);
    Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
    // Paid through the real money path, so the ledger holds it (as every real deposit is).
    $d = Deposit::create(['listing_id' => $l->id, 'user_id' => $u->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make($number), 'status' => 'awaiting']);
    app(DepositService::class)->receive($d, 5000000, 'bank_import', "test {$number}");
    BankAccount::create(['user_id' => $u->id, 'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => $account ?? (string) random_int(6200000000, 6299999999), 'account_last4' => '0000', 'account_type' => 'cheque', 'holder_name' => $name, 'status' => 'verified', 'locked' => true]);

    return $u;
}

function approvalOf(Listing $l, User $u): BidderApproval
{
    return BidderApproval::where('listing_id', $l->id)->where('user_id', $u->id)->firstOrFail();
}

it('closes an open auction: winner kept, runner-up held, others refunded, results sent', function () {
    $l = closeListing();
    [$a, $b, $c] = [paidBidder($l, 'Alice', '1111'), paidBidder($l, 'Bongani', '2222'), paidBidder($l, 'Carla', '3333')];
    $pending = User::factory()->create(['type' => 'buyer']);
    BidderApproval::create(['listing_id' => $l->id, 'user_id' => $pending->id, 'status' => 'pending']);
    $e = app(BidEngine::class);
    $e->place($l, $c, 1000000);
    $e->place($l, $b, 1100000);
    $e->place($l, $a, 1200000);

    // The original end and the live end both pass (extensions only ever move the live end later).
    $l->update(['ends_at' => now()->subSecond()]);
    AuctionState::query()->update(['ends_at' => now()->subSecond()]);
    $this->artisan('auctions:close')->assertSuccessful();
    $this->artisan('auctions:close')->assertSuccessful(); // idempotent

    $r = DB::table('auction_results')->where('listing_id', $l->id)->get();
    expect($r)->toHaveCount(1)->and($r[0]->winner_approval_id)->toBe(approvalOf($l, $a)->id)->and((int) $r[0]->winning_amount)->toBe(1200000)
        ->and($r[0]->runner_up_approval_id)->toBe(approvalOf($l, $b)->id)->and($l->fresh()->status)->toBe(ListingStatus::Sold);

    $refund = fn (User $u) => Refund::whereHas('deposit', fn ($q) => $q->where('user_id', $u->id))->first();
    expect(Deposit::where('user_id', $a->id)->value('status'))->toBe('received')                   // winner: kept for settlement
        ->and($refund($b)->reason)->toBe('runner_up_released')->and($refund($b)->due_on->toDateString())->toBe(today()->addDays(14)->toDateString())
        ->and($refund($c)->reason)->toBe('unsuccessful')
        ->and(BidderApproval::where('user_id', $pending->id)->value('status'))->toBe('rejected');
    Notification::assertSentTo($a, AuctionResult::class, fn ($n) => $n->kind === 'won');
    Notification::assertSentTo($b, AuctionResult::class, fn ($n) => $n->kind === 'runner_up');
    Notification::assertSentTo($c, AuctionResult::class, fn ($n) => $n->kind === 'lost');
});

it('waits for a soft-close extension and closes with no bids correctly', function () {
    $l = closeListing();
    $a = paidBidder($l, 'Alice', '1111');
    $l->update(['ends_at' => now()->subMinute()]);                     // original end passed…
    BidEngine::state($l)->update(['ends_at' => now()->addMinutes(3)]);  // …but soft close extended it

    $this->artisan('auctions:close');
    expect($l->fresh()->status)->toBe(ListingStatus::Live);

    AuctionState::query()->update(['ends_at' => now()->subSecond()]);
    $this->artisan('auctions:close');
    expect($l->fresh()->status)->toBe(ListingStatus::NoBids)
        ->and(Refund::whereHas('deposit', fn ($q) => $q->where('user_id', $a->id))->value('reason'))->toBe('unsuccessful');
});

it('keeps sealed offers raise-only and hidden until a two-person opening', function () {
    $l = closeListing('sealed');
    [$a, $b, $c] = [paidBidder($l, 'Alice', '1111'), paidBidder($l, 'Bongani', '2222'), paidBidder($l, 'Carla', '3333')];
    $t = app(TenderService::class);
    $err = function (Closure $f) {
        try {
            $f();
        } catch (ValidationException $e) {
            return array_keys($e->errors())[0];
        }

        return null;
    };

    expect($err(fn () => $t->submit($l, $a, 900000)))->toBe('amount');
    $t->submit($l, $a, 1500000);
    expect($err(fn () => $t->submit($l, $a, 1400000)))->toBe('amount');           // raise-only
    $this->travel(1)->seconds();
    $t->submit($l, $b, 1500000);                                                  // same amount, later: loses the tie
    $t->submit($l, $c, 1200000);

    $raw = (string) DB::table('tender_offers')->value('amount');
    expect($raw)->not->toContain('1500000');

    $staff1 = User::factory()->staff(SystemRole::Auctioneer)->create();
    $staff2 = User::factory()->staff(SystemRole::Auctioneer)->create();
    $this->actingAs($staff1)->get(route('admin.listings.auction', $l))->assertInertia(function (Assert $p) {
        $p->where('tender.count', 3)->where('tender.opened', false)->where('tender.offers', []);
        expect(json_encode($p->toArray()['props']))->not->toContain('1500000');
    });

    $this->travelTo($l->ends_at->addMinute());
    expect($err(fn () => $t->submit($l->fresh(), $a, 2000000)))->toBe('bid');
    $this->artisan('auctions:close');
    expect($l->fresh()->status)->toBe(ListingStatus::Closed);

    $this->actingAs($staff1)->post(route('admin.listings.tender.request', $l))->assertSessionHasNoErrors();
    $this->actingAs($staff1)->post(route('admin.listings.tender.confirm', $l))->assertSessionHasErrors('tender');
    $this->actingAs($staff2)->post(route('admin.listings.tender.confirm', $l))->assertSessionHasNoErrors();

    $r = DB::table('auction_results')->where('listing_id', $l->id)->first();
    expect($r->winner_approval_id)->toBe(approvalOf($l, $a)->id)->and($r->runner_up_approval_id)->toBe(approvalOf($l, $b)->id)
        ->and($l->fresh()->status)->toBe(ListingStatus::Sold)
        ->and(Refund::whereHas('deposit', fn ($q) => $q->where('user_id', $c->id))->value('reason'))->toBe('unsuccessful');
});

it('shows a bidder only their own sealed offer', function () {
    $l = closeListing('sealed');
    [$a, $b] = [paidBidder($l, 'Alice', '1111'), paidBidder($l, 'Bongani', '2222')];
    app(TenderService::class)->submit($l, $a, 1500000);
    app(TenderService::class)->submit($l, $b, 1700000);

    $this->actingAs($a)->get("/auctions/mining/emalahleni/{$l->slug}")->assertInertia(function (Assert $p) {
        $p->where('auction.kind', 'sealed')->where('auction.mine.offer', 1500000);
        expect(json_encode($p->toArray()['props']['auction']))->not->toContain('1700000');
    });
});

it('keeps a closed listing visible with its outcome and refuses bids', function () {
    $l = closeListing();
    $a = paidBidder($l, 'Alice', '1111');
    app(BidEngine::class)->place($l, $a, 1000000);
    $l->update(['ends_at' => now()->subSecond()]);
    AuctionState::query()->update(['ends_at' => now()->subSecond()]);
    $this->artisan('auctions:close');

    $this->get("/auctions/homes/emalahleni/{$l->slug}")->assertOk()->assertInertia(fn (Assert $p) => $p->where('auction.outcome', 'sold')->where('auction.closed', true));
    expect(fn () => app(BidEngine::class)->place($l->fresh(), $a, 1100000))->toThrow(ValidationException::class);

    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $this->actingAs($admin)->get(route('admin.listings.edit', $l))->assertOk(); // the edit lock handles the new statuses
});

it('flags shared IPs, shared bank accounts and bid pumping for review', function () {
    $l = closeListing();
    $a = paidBidder($l, 'Alice', '1111', '6211111111');
    $b = paidBidder($l, 'Bongani', '2222', '6211111111');
    $c = paidBidder($l, 'Carla', '3333');
    $e = app(BidEngine::class);
    $e->place($l, $c, 1000000, null, '10.0.0.1');
    foreach ([1025000, 1075000, 1125000] as $i => $amt) {
        $e->place($l, $b, $amt, null, '10.0.0.9');   // B always answered by A…
        $e->place($l, $a, $amt + 25000, null, '10.0.0.9');
    }

    $kinds = collect(ShillReport::for($l))->pluck('kind')->all();
    expect($kinds)->toContain('Shared IP address')->toContain('Shared bank account')->toContain('Possible bid pumping');
});

it('finishes the close for everyone when one deposit cannot be refunded', function () {
    $l = closeListing();
    [$a, $b, $c] = [paidBidder($l, 'Alice', '1111'), paidBidder($l, 'Bongani', '2222'), paidBidder($l, 'Carla', '3333')];
    app(BidEngine::class)->place($l, $a, 1000000);
    Deposit::where('user_id', $b->id)->first()->forceFill(['status' => 'received'])->save();
    LedgerEntry::create(['deposit_id' => Deposit::where('user_id', $b->id)->value('id'), 'type' => 'adjustment', 'amount' => -5000000, 'source' => 'manual', 'created_at' => now()]); // nothing held for B
    $l->update(['ends_at' => now()->subSecond()]);
    AuctionState::query()->update(['ends_at' => now()->subSecond()]);

    $this->artisan('auctions:close')->assertSuccessful();

    expect($l->fresh()->status)->toBe(ListingStatus::Sold)
        ->and(Refund::whereHas('deposit', fn ($q) => $q->where('user_id', $c->id))->exists())->toBeTrue()
        ->and(AuditEntry::where('event', 'refund_not_scheduled')->exists())->toBeTrue();
    Notification::assertSentTo($c, AuctionResult::class);
});
