<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Privacy\Settings\PrivacySettings;
use App\Domains\Privacy\Support\RetentionSchedule;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
});

/** A buyer whose last sign-in was $yearsAgo years ago, with a verification document on disk. */
function formerBuyer(int $yearsAgo, string $email): User
{
    $u = User::factory()->create(['type' => 'buyer', 'email' => $email, 'last_login_at' => now()->subYears($yearsAgo), 'created_at' => now()->subYears($yearsAgo + 1)]);
    $v = DB::table('verifications')->insertGetId(['ulid' => (string) Str::ulid(), 'user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'created_at' => now(), 'updated_at' => now()]);
    Storage::disk('local')->put("kyc/{$u->id}.bin", 'encrypted-bytes');
    DB::table('verification_documents')->insert(['verification_id' => $v, 'kind' => 'id_front', 'path' => "kyc/{$u->id}.bin", 'mime' => 'image/jpeg', 'size' => 15, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('screenings')->insert(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'UN', 'screened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    return $u;
}

it('reports only ended relationships, never anyone with money still held', function () {
    $old = formerBuyer(6, 'old@example.com');
    formerBuyer(2, 'recent@example.com');
    $held = formerBuyer(6, 'held@example.com');
    $l = Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => 'Home', 'slug' => 'home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
    $ap = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $held->id, 'status' => 'approved', 'bidder_number' => '1111']);
    $d = Deposit::create(['listing_id' => $l->id, 'user_id' => $held->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make('1111'), 'status' => 'awaiting']);
    app(DepositService::class)->receive($d, 5000000, 'bank_import', 'x');

    expect(RetentionSchedule::endedRelationships()->all())->toBe([$old->id])
        ->and(RetentionSchedule::due()['kyc'])->toBe(2); // one verification + one screening

    $this->artisan('privacy:retention')->assertSuccessful();
    expect(DB::table('verifications')->count())->toBe(3)->and(AuditEntry::where('event', 'retention_report')->exists())->toBeTrue();
});

it('refuses to purge until the attorney’s approval is recorded, then purges only what is due', function () {
    $old = formerBuyer(6, 'old@example.com');
    $recent = formerBuyer(2, 'recent@example.com');
    DB::table('newsletter_subscribers')->insert(['ulid' => (string) Str::ulid(), 'email' => 'stale@example.com', 'status' => 'pending', 'consent_at' => now()->subDays(40), 'created_at' => now()->subDays(40), 'updated_at' => now()]);
    $event = DB::table('events')->insertGetId(['ulid' => (string) Str::ulid(), 'title' => 'Old event', 'slug' => 'old-event', 'kind' => 'in_person', 'venue' => 'x', 'starts_at' => now()->subYears(3), 'ends_at' => now()->subYears(3), 'price' => 15000, 'capacity' => 10, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('ticket_orders')->insert(['ulid' => $tu = (string) Str::ulid(), 'event_id' => $event, 'reference' => 'TDG-E-OLD00001', 'name' => 'Naledi Dube', 'email' => 'naledi@example.com', 'mobile' => '0825550000', 'quantity' => 1, 'amount' => 15000, 'status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('privacy:retention --purge')->assertFailed();
    $this->artisan('privacy:retention --scheduled')->assertSuccessful(); // not approved: report only
    expect(DB::table('verifications')->count())->toBe(2);

    $s = app(PrivacySettings::class);
    $s->purge_enabled = true;
    $s->approval_reference = 'Attorney letter 2026-12-01';
    $s->save();
    $this->artisan('privacy:retention --scheduled')->assertSuccessful();

    expect(DB::table('verifications')->where('user_id', $old->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists("kyc/{$old->id}.bin"))->toBeFalse()
        ->and(DB::table('verifications')->where('user_id', $recent->id)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists("kyc/{$recent->id}.bin"))->toBeTrue()
        ->and(User::find($old->id))->not->toBeNull()                       // the account (and its financial records) remain
        ->and(DB::table('newsletter_subscribers')->count())->toBe(0)
        ->and(DB::table('ticket_orders')->value('email'))->toBe("removed+{$tu}@invalid")
        ->and(DB::table('ticket_orders')->value('amount'))->toBe(15000)
        ->and(AuditEntry::where('event', 'retention_purge')->exists())->toBeTrue();
});

it('lets only a super admin enable purging, with an approval reference', function () {
    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $other = User::factory()->staff(SystemRole::Finance)->create();
    $other->givePermissionTo('settings.manage');

    $this->actingAs($other)->get(route('admin.settings.retention'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.settings.retention'))->assertOk()->assertInertia(fn ($p) => $p->has('categories', 6)->where('approval.enabled', false));
    $this->actingAs($admin)->put(route('admin.settings.retention.update'), ['enabled' => true])->assertSessionHasErrors('reference');
    $this->actingAs($admin)->put(route('admin.settings.retention.update'), ['enabled' => true, 'reference' => 'Attorney letter 2026-12-01'])->assertSessionHasNoErrors();
    expect(app(PrivacySettings::class)->purge_enabled)->toBeTrue()->and(AuditEntry::where('event', 'retention_purge_enabled')->exists())->toBeTrue();
});

it('starts every artisan command even before new settings exist', function () {
    // Regression: reading settings in routes/console.php broke `migrate` itself on first deploy.
    DB::table('settings')->where('group', 'privacy')->delete();
    app()->forgetInstance(PrivacySettings::class);

    expect(Artisan::call('schedule:list'))->toBe(0);
});
