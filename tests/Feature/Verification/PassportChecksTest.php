<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Support\Countries;
use App\Domains\Verification\Support\SaIdNumber;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Tendai', 'last_name' => 'Moyo']);
});

function passport(array $overrides = []): array
{
    return array_merge(['document_type' => 'passport', 'id_number' => 'fn 123 4567', 'passport_country' => 'ZW',
        'date_of_birth' => now()->subYears(34)->toDateString(), 'passport_expires_on' => now()->addYears(3)->toDateString(),
        'front' => UploadedFile::fake()->image('passport.jpg')], $overrides);
}

it('accepts a valid passport and stores its details', function () {
    $this->actingAs($this->buyer)->post(route('account.verification.identity'), passport())->assertSessionHasNoErrors();

    $v = Verification::where('user_id', $this->buyer->id)->firstOrFail();
    expect($v->id_number)->toBe('FN1234567')                       // spaces removed, capitals
        ->and($v->passport_country)->toBe('ZW')
        ->and($v->passport_expires_on->toDateString())->toBe(now()->addYears(3)->toDateString())
        ->and($v->date_of_birth->age)->toBe(34);
});

it('refuses passports that are expired, for under-18s, with a bad number or an unknown country', function () {
    $cases = [
        'passport_expires_on' => ['passport_expires_on' => now()->subDay()->toDateString()],
        'date_of_birth' => ['date_of_birth' => now()->subYears(17)->toDateString()],
        'id_number' => ['id_number' => 'AB12'],
        'passport_country' => ['passport_country' => 'XX'],
    ];
    foreach ($cases as $field => $override) {
        $this->actingAs($this->buyer)->post(route('account.verification.identity'), passport($override))->assertSessionHasErrors($field);
    }
    $this->actingAs($this->buyer)->post(route('account.verification.identity'), passport(['id_number' => 'AB12$%!@']))->assertSessionHasErrors('id_number');
    $this->actingAs($this->buyer)->post(route('account.verification.identity'), passport(['passport_expires_on' => null, 'date_of_birth' => null]))
        ->assertSessionHasErrors(['passport_expires_on', 'date_of_birth']);
    expect(Verification::where('user_id', $this->buyer->id)->whereNotNull('id_number')->exists())->toBeFalse();
});

it('refuses an SA ID number belonging to someone under 18', function () {
    $dob = now()->subYears(16)->format('ymd');
    $minor = collect(range(0, 9))->map(fn ($c) => $dob.'5000080'.$c)->first(fn ($n) => strlen($n) === 13 && SaIdNumber::isValid($n))
        ?? collect(range(5000, 5099))->flatMap(fn ($s) => collect(range(0, 9))->map(fn ($c) => $dob.$s.'08'.$c))->first(fn ($n) => SaIdNumber::isValid($n));
    expect($minor)->not->toBeNull();

    $this->actingAs($this->buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => $minor,
        'front' => UploadedFile::fake()->image('id.jpg')])->assertSessionHasErrors('id_number');
});

it('lists countries by name for the dropdown, without South Africa', function () {
    $c = Countries::forPassports();
    expect($c)->toHaveKey('ZW')->and($c['ZW'])->toBe('Zimbabwe')->and($c)->not->toHaveKey('ZA')->and(count($c))->toBeGreaterThan(200);
});

it('shows reviewers the expiry, the age, and auctions that settle after the passport expires', function () {
    $this->actingAs($this->buyer)->post(route('account.verification.identity'), passport(['passport_expires_on' => now()->addDays(40)->toDateString()]))->assertSessionHasNoErrors();
    $l = Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'sandton')->value('id'), 'title' => 'Family home', 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30)]);
    BidderApproval::create(['listing_id' => $l->id, 'user_id' => $this->buyer->id, 'status' => 'pending']);

    $officer = User::factory()->staff(SystemRole::KycOfficer)->create();
    $v = Verification::where('user_id', $this->buyer->id)->firstOrFail();
    $this->actingAs($officer)->get(route('admin.kyc.show', $v))->assertOk()->assertInertia(fn ($p) => $p
        ->where('verification.age', 34)
        ->where('verification.passportCountryName', 'Zimbabwe')
        ->where('verification.passportDaysLeft', 40)
        ->where('verification.settlementConflicts.0.listing', 'Family home'));
});
