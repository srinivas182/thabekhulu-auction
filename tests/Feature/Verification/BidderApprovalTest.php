<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\BiddingEntity;
use App\Domains\Verification\Models\ProofOfFunds;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Notifications\BidderUpdate;
use App\Domains\Verification\Support\BidderEligibility;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create();
    $this->officer = User::factory()->staff(SystemRole::KycOfficer)->create();
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Naledi', 'last_name' => 'Dube']);
});

function auctionListing(string $category = 'mining', int $minimum = 2300000, string $slug = 'x'): Listing
{
    static $n = 0;
    $n++;

    return Listing::create([
        'category_id' => Category::where('slug', $category)->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'),
        'title' => "Listing {$n}", 'slug' => "{$slug}-{$n}", 'deal_type' => 'commission', 'seller_net_price' => 1000000, 'fee_bps' => 2000,
        'minimum_price' => $minimum, 'deposit_amount' => 50000, 'deposit_terms' => 'security', 'auction_type' => 'open',
        'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
    ]);
}

/** A verified, screened buyer with a verified refund account. */
function readyBuyer(User $buyer): void
{
    Verification::create(['user_id' => $buyer->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    Screening::create(['user_id' => $buyer->id, 'result' => 'clear', 'lists' => 'FIC TFS, UN, PEP', 'screened_at' => now()]);
    BankAccount::create(['user_id' => $buyer->id, 'bank' => 'Capitec', 'branch_code' => '470010', 'account_number' => '1234567890', 'account_last4' => '7890',
        'account_type' => 'savings', 'holder_name' => 'Naledi Dube', 'status' => 'verified', 'verified_at' => now()]);
}

function pdf(): UploadedFile
{
    return UploadedFile::fake()->create('doc.pdf', 40, 'application/pdf');
}

it('requires proof of funds for mining and for minimums at or above the threshold', function () {
    $e = app(BidderEligibility::class);

    expect($e->needsProofOfFunds(auctionListing('mining', 100000)))->toBeTrue()
        ->and($e->needsProofOfFunds(auctionListing('homes', 4999999)))->toBeFalse()
        ->and($e->needsProofOfFunds(auctionListing('homes', 5000000)))->toBeTrue();
});

it('blocks approval until every required item is complete, then assigns a bidder number', function () {
    $listing = auctionListing('mining');

    $this->actingAs($this->buyer)->post(route('auctions.apply', $listing))->assertSessionHasErrors(['funds', 'funds_amount']);
    $this->actingAs($this->buyer)->post(route('auctions.apply', $listing), ['funds_amount' => 1000, 'funds' => pdf()])->assertSessionHasErrors('funds_amount');
    $this->actingAs($this->buyer)->post(route('auctions.apply', $listing), ['funds_amount' => 3000000, 'funds' => pdf()])->assertSessionHasNoErrors();

    $approval = BidderApproval::firstOrFail();
    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'approve'])->assertSessionHasErrors('decision');

    readyBuyer($this->buyer);
    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'approve'])->assertSessionHasErrors('decision'); // funds still pending

    $this->actingAs($this->officer)->post(route('admin.kyc.funds.decide', ProofOfFunds::firstOrFail()), ['decision' => 'accept', 'amount' => 3000000])->assertSessionHasNoErrors();
    expect(ProofOfFunds::first()->valid_until->toDateString())->toBe(now()->addDays(90)->toDateString());

    $checklist = app(BidderEligibility::class)->checklist($approval->fresh());
    expect(collect($checklist)->pluck('state', 'key')->all())->toMatchArray(['identity' => 'ok', 'screening' => 'ok', 'bank' => 'ok', 'funds' => 'ok', 'deposit' => 'later', 'nda' => 'missing']);
    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'approve'])->assertSessionHasErrors('decision'); // NDA not signed

    $this->actingAs($this->buyer)->post(route('dataroom.nda', $listing->ulid), ['typed_name' => 'Naledi Dube', 'agree' => '1'])->assertSessionHasNoErrors();

    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'approve'])->assertSessionHasNoErrors();
    $approval->refresh();
    expect($approval->status)->toBe('approved')->and($approval->bidder_number)->toMatch('/^\d{4}$/')->and($approval->checklist)->not->toBeEmpty();
    Notification::assertSentTo($this->buyer, BidderUpdate::class, fn ($n) => $n->kind === 'approve' && str_contains($n->line, $approval->bidder_number));
});

it('approves bidders per listing only', function () {
    readyBuyer($this->buyer);
    $a = auctionListing('homes', 900000);
    $b = auctionListing('homes', 900000);

    $this->actingAs($this->buyer)->post(route('auctions.apply', $a));
    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', BidderApproval::firstOrFail()), ['decision' => 'approve']);

    $this->actingAs($this->buyer)->get("/auctions/homes/emalahleni/{$a->slug}")->assertInertia(fn (Assert $p) => $p->where('bidder.approval', 'approved')->where('bidder.bidderNumber', fn ($n) => strlen($n) === 4));
    $this->actingAs($this->buyer)->get("/auctions/homes/emalahleni/{$b->slug}")->assertInertia(fn (Assert $p) => $p->where('bidder.approval', null));
    auth()->logout();
    $this->get("/auctions/homes/emalahleni/{$a->slug}")->assertInertia(fn (Assert $p) => $p->where('bidder', null)); // guests see no bidder state
});

it('reuses accepted, valid proof of funds and rejects expired proof', function () {
    readyBuyer($this->buyer);
    ProofOfFunds::create(['user_id' => $this->buyer->id, 'amount' => 5000000, 'path' => 'x', 'mime' => 'application/pdf', 'status' => 'accepted', 'valid_until' => now()->addDays(10)]);
    $listing = auctionListing('mining', 2000000);

    $this->actingAs($this->buyer)->post(route('auctions.apply', $listing))->assertSessionHasNoErrors();
    expect(app(BidderEligibility::class)->checklist(BidderApproval::firstOrFail())[3]['state'])->toBe('ok');

    ProofOfFunds::query()->update(['valid_until' => now()->subDay()]);
    expect(collect(app(BidderEligibility::class)->checklist(BidderApproval::firstOrFail()->fresh()))->firstWhere('key', 'funds')['state'])->toBe('missing');
});

it('refuses a second application while one is pending or approved, and allows reapplying after rejection', function () {
    $listing = auctionListing('homes', 900000);
    $this->actingAs($this->buyer)->post(route('auctions.apply', $listing));
    $this->actingAs($this->buyer)->post(route('auctions.apply', $listing))->assertSessionHasErrors('apply');

    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', BidderApproval::firstOrFail()), ['decision' => 'reject', 'reason' => 'Identity not verified yet.']);
    $this->actingAs($this->buyer)->post(route('auctions.apply', $listing))->assertSessionHasNoErrors();
    expect(BidderApproval::count())->toBe(1)->and(BidderApproval::first()->status)->toBe('pending');
});

it('registers a company with its controllers encrypted and requires a verified entity to approve', function () {
    readyBuyer($this->buyer);
    $docs = ['registration' => pdf(), 'resolution' => pdf(), 'ownership' => pdf()];

    $this->actingAs($this->buyer)->post(route('account.entities.store'), [
        'type' => 'company', 'name' => 'Highveld Resources (Pty) Ltd', 'registration_number' => '2015-123456',
        'controllers' => [['name' => 'Naledi Dube', 'role' => 'Director', 'id_number' => '8001015009087', 'ownership' => 60]], 'documents' => $docs,
    ])->assertSessionHasErrors('registration_number');

    $this->actingAs($this->buyer)->post(route('account.entities.store'), [
        'type' => 'company', 'name' => 'Highveld Resources (Pty) Ltd', 'registration_number' => '2015/123456/07',
        'controllers' => [['name' => 'Naledi Dube', 'role' => 'Director', 'id_number' => '8001015009087', 'ownership' => 60], ['name' => 'Sipho Ndlovu', 'role' => 'Shareholder', 'id_number' => 'A1234567', 'ownership' => 40]],
        'documents' => $docs,
    ])->assertSessionHasNoErrors();

    $entity = BiddingEntity::firstOrFail();
    expect($entity->controllers[0]['name'])->toBe('Naledi Dube')->and($entity->documents()->count())->toBe(3)
        ->and((string) DB::table('bidding_entities')->value('controllers'))->not->toContain('Naledi');

    $listing = auctionListing('homes', 900000);
    $this->actingAs($this->buyer)->post(route('auctions.apply', $listing), ['entity' => $entity->ulid]);
    $approval = BidderApproval::firstOrFail();
    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'approve'])->assertSessionHasErrors('decision');

    $this->actingAs($this->auctioneer)->post(route('admin.kyc.entity.decide', $entity), ['decision' => 'verify'])->assertForbidden();
    $this->actingAs($this->officer)->post(route('admin.kyc.entity.decide', $entity), ['decision' => 'verify'])->assertSessionHasNoErrors();
    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'approve'])->assertSessionHasNoErrors();
});

it('withdraws an approval with a reason', function () {
    readyBuyer($this->buyer);
    $this->actingAs($this->buyer)->post(route('auctions.apply', auctionListing('homes', 900000)));
    $approval = BidderApproval::firstOrFail();
    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'approve']);

    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'revoke'])->assertSessionHasErrors('reason');
    $this->actingAs($this->auctioneer)->post(route('admin.bidders.decide', $approval), ['decision' => 'revoke', 'reason' => 'Screening match found.'])->assertSessionHasNoErrors();
    expect($approval->fresh()->status)->toBe('revoked');
});

it('limits the bidders page and decisions to staff who may approve bidders', function () {
    $listing = auctionListing('homes', 900000);

    $this->actingAs($this->officer)->get(route('admin.listings.bidders', $listing))->assertForbidden();
    $this->actingAs($this->auctioneer)->get(route('admin.listings.bidders', $listing))->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/listings/bidders'));
    $this->actingAs($this->buyer)->get(route('account.approvals'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('account/approvals'));
});
