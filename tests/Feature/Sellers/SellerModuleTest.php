<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Identity\Enums\UserType;
use App\Domains\Leads\Models\Lead;
use App\Domains\Listings\Actions\ListingWorkflow;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Platform\Features\Flags;
use App\Domains\Sellers\Models\SellerPackage;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function seller(): User
{
    return User::query()->forceCreate(['type' => UserType::Seller, 'first_name' => 'Sizwe', 'last_name' => 'Mahlangu', 'email' => 'sizwe'.uniqid().'@example.com', 'mobile' => '+27825550101', 'password' => 'Secret-pass-2026', 'email_verified_at' => now(), 'popia_consent_at' => now()]);
}

it('hides every seller page while the flag is off', function () {
    expect(Flags::active('sellers'))->toBeFalse();
    $s = seller();

    $this->get('/sell/register')->assertNotFound();
    $this->actingAs($s)->get('/seller')->assertNotFound();
    $this->actingAs($this->admin)->get(route('admin.sellers.packages'))->assertNotFound();
});

it('registers a seller with consent when the flag is on', function () {
    Flags::set('sellers', true);
    $this->get('/sell/register')->assertOk();

    $payload = ['first_name' => 'Sizwe', 'last_name' => 'Mahlangu', 'email' => 'sizwe@example.com', 'mobile' => '+27825550101', 'password' => 'Very-strong-pass-2026', 'password_confirmation' => 'Very-strong-pass-2026'];
    $this->post(route('seller.register.store'), $payload)->assertSessionHasErrors('popia');
    $this->post(route('seller.register.store'), $payload + ['popia' => '1'])->assertRedirect(route('seller.dashboard'));

    $u = User::where('email', 'sizwe@example.com')->firstOrFail();
    expect($u->isSeller())->toBeTrue()->and($u->popia_consent_at)->not->toBeNull();
    $this->get('/seller')->assertOk();
});

it('turns a seller submission into a lead and then a draft listing linked to the seller', function () {
    Flags::set('sellers', true);
    $s = seller();
    $this->actingAs($s)->post(route('seller.submit'), [
        'property_type' => 'Home', 'location' => 'Midrand', 'asking_price' => 1800000, 'description' => 'Four-bedroom family home.',
        'documents' => [['kind' => 'title_deed', 'file' => UploadedFile::fake()->create('deed.pdf', 40, 'application/pdf')]],
    ])->assertSessionHasNoErrors();

    $lead = Lead::firstOrFail();
    $doc = DB::table('lead_documents')->first();
    expect($lead->user_id)->toBe($s->id)->and((string) Storage::disk('local')->get($doc->path))->not->toContain('%PDF');

    $this->actingAs($this->admin)->get(route('admin.leads.document', [$lead, $doc->id]))->assertOk();
    expect(AuditEntry::where('event', 'document_viewed')->exists())->toBeTrue();

    $homes = Category::where('slug', 'homes')->value('id');
    $this->actingAs($this->admin)->post(route('admin.leads.convert', $lead), ['category_id' => $homes, 'title' => 'Family home, Midrand'])->assertRedirect();
    $listing = Listing::firstOrFail();
    expect($listing->seller_user_id)->toBe($s->id)->and($listing->status)->toBe(ListingStatus::Draft)
        ->and($lead->fresh()->status)->toBe('converted')->and($lead->fresh()->listing_id)->toBe($listing->id);

    $this->actingAs($this->admin)->post(route('admin.leads.convert', $lead), ['category_id' => $homes, 'title' => 'Again'])->assertStatus(422);
    $this->actingAs($s)->get('/seller')->assertInertia(fn ($p) => $p->has('listings', 1)->where('listings.0.title', 'Family home, Midrand'));
});

it('blocks approving an auction that ends after the seller’s package', function () {
    Flags::set('sellers', true);
    $s = seller();
    $pkg = SellerPackage::create(['name' => 'Standard', 'price' => 500000, 'duration_days' => 30, 'max_listings' => 1]);
    $this->actingAs($this->admin)->post(route('admin.sellers.packages.assign'), ['user_id' => $s->id, 'seller_package_id' => $pkg->id, 'starts_on' => today()->toDateString()])->assertSessionHasNoErrors();
    $listing = Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'title' => 'Seller home', 'slug' => 'seller-home', 'deal_type' => 'commission',
        'fee_bps' => 2000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied', 'auction_type' => 'open', 'status' => ListingStatus::InReview,
        'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(45), 'seller_user_id' => $s->id]);

    $err = null;
    try {
        app(ListingWorkflow::class)->approve($this->admin, $listing);
    } catch (ValidationException $e) {
        $err = implode(' ', $e->errors()['listing'] ?? []);
    }
    expect($err)->toContain('package expires');

    DB::table('seller_package_assignments')->delete();
    $err = null;
    try {
        app(ListingWorkflow::class)->approve($this->admin, $listing->fresh());
    } catch (ValidationException $e) {
        $err = implode(' ', $e->errors()['listing'] ?? []);
    }
    expect($err)->toContain('no active package');
});

it('keeps one account per ID across buyers and sellers, and keeps buyers out of seller pages', function () {
    Flags::set('sellers', true);
    $buyer = User::factory()->create(['type' => 'buyer']);
    $this->actingAs($buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '8001015009087', 'front' => UploadedFile::fake()->image('id.jpg')])->assertSessionHasNoErrors();

    $s = seller();
    $this->actingAs($s)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '8001015009087', 'front' => UploadedFile::fake()->image('id.jpg')])->assertSessionHasErrors('id_number');
    $this->actingAs($buyer)->get('/seller')->assertForbidden();
});
