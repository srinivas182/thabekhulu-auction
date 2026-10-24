<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\DataRoom\Settings\DataRoomSettings;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->kyc = User::factory()->staff(SystemRole::KycOfficer)->create();
    $this->finance = User::factory()->staff(SystemRole::Finance)->create();
    $this->listing = Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => 'Family home', 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
});

function riskyBuyer(string $name, string $number): array
{
    $u = User::factory()->create(['type' => 'buyer', 'first_name' => $name, 'last_name' => 'Risk', 'email' => strtolower($name).'@example.com']);
    $ap = BidderApproval::create(['listing_id' => test()->listing->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => $number]);

    return [$u, $ap];
}

it('gathers each kind of signal once', function () {
    [$a, $apA] = riskyBuyer('Anele', '1111');
    [$b, $apB] = riskyBuyer('Bheki', '2222');
    foreach ([[$apA, 1000000], [$apB, 1025000]] as [$ap, $amt]) {  // two bidders from one IP address
        Bid::create(['listing_id' => $this->listing->id, 'bidder_approval_id' => $ap->id, 'bidder_number' => $ap->bidder_number, 'amount' => $amt, 'kind' => 'manual', 'placed_at' => now(), 'ip_address' => '10.9.9.9']);
    }
    DB::table('screenings')->insert(['user_id' => $a->id, 'result' => 'possible_match', 'lists' => 'UN', 'screened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $doc = DB::table('listing_documents')->insertGetId(['listing_id' => $this->listing->id, 'document_type_id' => DB::table('document_types')->value('id'), 'path' => 'x', 'original_name' => 'deed.pdf',
        'mime' => 'application/pdf', 'size' => 1000, 'uploaded_by' => $this->finance->id, 'created_at' => now(), 'updated_at' => now()]);
    for ($i = 0; $i < app(DataRoomSettings::class)->daily_download_alert; $i++) {
        DB::table('document_downloads')->insert(['listing_document_id' => $doc, 'listing_id' => $this->listing->id, 'user_id' => $b->id, 'created_at' => now()]);
    }
    $import = DB::table('bank_imports')->insertGetId(['file_name' => 's.csv', 'file_hash' => str_repeat('b', 64), 'format' => 'csv', 'lines_total' => 1, 'matched' => 0, 'queued' => 1, 'duplicates' => 0,
        'imported_by' => $this->finance->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('bank_lines')->insert(['bank_import_id' => $import, 'line_hash' => str_repeat('a', 64), 'posted_on' => today()->subDays(5), 'amount' => 777700, 'description' => 'UNKNOWN EFT', 'status' => 'unmatched', 'created_at' => now()->subDays(4), 'updated_at' => now()]);
    for ($i = 0; $i < 3; $i++) {
        Audit::record('auth', 'lockout', 'Sign-in locked', null, null, ['email' => 'Bheki@example.com']);
    }
    DB::table('bid_otps')->insert(['user_id' => $a->id, 'listing_id' => $this->listing->id, 'code_hash' => 'x', 'attempts' => 5, 'expires_at' => now()->addMinutes(5), 'created_at' => now()]);

    $this->artisan('compliance:scan')->assertSuccessful();
    $this->artisan('compliance:scan')->assertSuccessful();

    $sources = DB::table('suspicious_items')->pluck('source')->countBy()->all();
    expect($sources)->toMatchArray(['integrity' => 1, 'screening' => 1, 'downloads' => 1, 'bank_line' => 1, 'lockout' => 1, 'bid_code' => 1])
        ->and(DB::table('suspicious_items')->count())->toBe(6);
});

it('lets compliance reviewers decide with a reason and export evidence', function () {
    [$a] = riskyBuyer('Anele', '1111');
    Audit::record('bidding', 'bid', 'Bid placed', $this->listing, $a);
    DB::table('suspicious_items')->insert(['ulid' => $ulid = (string) Str::ulid(), 'source' => 'screening', 'fingerprint' => 'screening:1', 'user_id' => $a->id, 'listing_id' => $this->listing->id,
        'summary' => 'Possible match', 'details' => json_encode(['lists' => 'UN']), 'status' => 'open', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($this->finance)->get(route('admin.compliance.index'))->assertForbidden();
    $this->actingAs($this->kyc)->get(route('admin.compliance.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('items', 1));
    $this->actingAs($this->kyc)->put(route('admin.compliance.update', $ulid), ['status' => 'escalated'])->assertSessionHasErrors('note');
    $this->actingAs($this->kyc)->put(route('admin.compliance.update', $ulid), ['status' => 'escalated', 'note' => 'Name matches UN list entry; escalated to the compliance officer.'])->assertSessionHasNoErrors();
    expect(DB::table('suspicious_items')->value('decided_by'))->toBe($this->kyc->id);

    $pack = json_decode($this->actingAs($this->kyc)->get(route('admin.compliance.evidence', $ulid))->assertOk()->getContent(), true);
    expect($pack['note'])->toContain('Financial Intelligence Centre')->and($pack['notes'])->toHaveCount(1)
        ->and(collect($pack['audit_trail'])->pluck('event'))->toContain('bid')
        ->and(AuditEntry::where('event', 'evidence_exported')->exists())->toBeTrue();
});

it('shows each role only its register columns and audits exports', function () {
    [$a, $ap] = riskyBuyer('Anele', '1111');
    Verification::create(['user_id' => $a->id, 'status' => 'verified', 'document_type' => 'sa_id', 'id_number' => '8001015009087', 'verified_at' => now()]);
    DB::table('screenings')->insert(['user_id' => $a->id, 'result' => 'clear', 'lists' => 'UN', 'screened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $d = Deposit::create(['listing_id' => $this->listing->id, 'user_id' => $a->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make('1111'), 'status' => 'awaiting']);
    app(DepositService::class)->receive($d, 5000000, 'bank_import', 'x');
    $viewer = User::factory()->staff(SystemRole::Support)->create();
    $viewer->syncPermissions(['buyers.view']);
    $kycViewer = User::factory()->staff(SystemRole::KycOfficer)->create();
    $kycViewer->givePermissionTo('buyers.view');
    $financeViewer = User::factory()->staff(SystemRole::Finance)->create();
    $financeViewer->givePermissionTo('buyers.view');

    $this->actingAs($kycViewer)->get(route('admin.reports.register'))->assertInertia(fn (Assert $p) => $p->where('rows.0.id_last4', '9087')->where('rows.0.approved_for', 1)->missing('rows.0.deposits_held_cents'));
    $this->actingAs($financeViewer)->get(route('admin.reports.register'))->assertInertia(fn (Assert $p) => $p->where('rows.0.deposits_held_cents', 5000000)->missing('rows.0.id_last4'));
    $this->actingAs($viewer)->get(route('admin.reports.register'))->assertInertia(fn (Assert $p) => $p->missing('rows.0.id_last4')->missing('rows.0.deposits_held_cents'));

    $this->actingAs($viewer)->get(route('admin.reports.register.csv'))->assertForbidden();
    $csv = $this->actingAs($financeViewer)->get(route('admin.reports.register.csv'))->assertOk()->streamedContent();
    expect($csv)->toContain('deposits_held_cents')->not->toContain('id_last4')->and(AuditEntry::where('event', 'register_exported')->exists())->toBeTrue();
});

it('reports the period’s money and exports it for the accountant', function () {
    [$a, $ap] = riskyBuyer('Anele', '1111');
    $d = Deposit::create(['listing_id' => $this->listing->id, 'user_id' => $a->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make('1111'), 'status' => 'awaiting']);
    app(DepositService::class)->receive($d, 5000000, 'bank_import', 'x');

    $this->actingAs(User::factory()->staff(SystemRole::Support)->create())->get(route('admin.reports.finance'))->assertForbidden();
    $this->actingAs($this->finance)->get(route('admin.reports.finance'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('figures.received', 5000000)->where('figures.heldAtEnd', 5000000)->where('checks.0.ok', true));

    $csv = $this->actingAs($this->finance)->get(route('admin.reports.finance.csv', ['report' => 'ledger']))->assertOk()->streamedContent();
    expect($csv)->toContain($d->reference)->toContain('50000.00');
    $this->actingAs($this->finance)->get(route('admin.reports.finance.csv', ['report' => 'secrets']))->assertNotFound();
});
