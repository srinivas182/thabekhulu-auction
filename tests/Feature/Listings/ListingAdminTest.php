<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->manager = User::factory()->staff(SystemRole::ListingsManager)->create();
    $this->auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create();
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $this->land = Category::where('slug', 'land')->firstOrFail();
});

function newListing(User $by, string $category = 'land'): Listing
{
    test()->actingAs($by)->post(route('admin.listings.store'), [
        'category_id' => Category::where('slug', $category)->value('id'),
        'title' => '10 ha farm portion with river frontage',
    ]);

    return Listing::latest('id')->firstOrFail();
}

/** Fills every section so the listing is ready for review. */
function completeListing(User $by, Listing $listing): Listing
{
    test()->actingAs($by)->put(route('admin.listings.update', [$listing, 'details']), [
        'title' => $listing->title, 'city_id' => City::where('slug', 'polokwane')->value('id'),
        'summary' => 'A 10 ha farm portion near Polokwane.', 'description' => '<p>River frontage.</p>',
    ])->assertSessionHasNoErrors();

    $facts = [];
    foreach ($listing->category->attributeDefinitions as $f) {
        $facts[$f->id] = match ($f->type->value) {
            'number' => '10', 'select' => $f->options[0], 'boolean' => 'yes', 'date' => '2030-01-01', default => 'Tar road'
        };
    }
    test()->actingAs($by)->put(route('admin.listings.update', [$listing, 'facts']), ['facts' => $facts])->assertSessionHasNoErrors();

    test()->actingAs($by)->put(route('admin.listings.update', [$listing, 'pricing']), [
        'deal_type' => 'commission', 'seller_net_price' => 1800000, 'fee_percent' => 20,
        'minimum_price' => 2300000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
    ])->assertSessionHasNoErrors();

    test()->actingAs($by)->put(route('admin.listings.update', [$listing, 'auction']), [
        'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'auction_type' => 'open',
    ])->assertSessionHasNoErrors();

    test()->actingAs($by)->post(route('admin.listings.media.store', [$listing, 'photos']), [
        'files' => [UploadedFile::fake()->image('a.jpg', 1200, 800), UploadedFile::fake()->image('b.jpg', 1200, 800), UploadedFile::fake()->image('c.jpg', 1200, 800)],
    ])->assertSessionHasNoErrors();
    foreach ($listing->fresh()->getMedia('photos') as $m) {
        test()->actingAs($by)->put(route('admin.listings.media.update', [$listing, $m]), ['alt' => 'View of the farm']);
    }

    foreach ($listing->category->documentTypes->where('is_mandatory', true) as $type) {
        test()->actingAs($by)->post(route('admin.listings.documents.store', [$listing, $type]), [
            'file' => UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }

    return $listing->fresh();
}

it('creates drafts with the category defaults', function () {
    $mining = newListing($this->manager, 'mining');

    expect($mining->status)->toBe(ListingStatus::Draft)
        ->and($mining->deposit_amount)->toBe(1200000)
        ->and($mining->deposit_terms->value)->toBe('security')
        ->and($mining->fee_bps)->toBe(2000)
        ->and($mining->ulid)->toHaveLength(26);
});

it('enforces the minimum selling price floor for both deal types', function () {
    $listing = newListing($this->manager);

    $this->actingAs($this->manager)->put(route('admin.listings.update', [$listing, 'pricing']), [
        'deal_type' => 'commission', 'seller_net_price' => 1800000, 'fee_percent' => 20,
        'minimum_price' => 2249999, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
    ])->assertSessionHasErrors('minimum_price');

    $this->actingAs($this->manager)->put(route('admin.listings.update', [$listing, 'pricing']), [
        'deal_type' => 'renovated', 'seller_price' => 50000, 'renovation_cost' => 30000, 'fee_percent' => 0,
        'minimum_price' => 80000, 'deposit_amount' => 5000, 'deposit_terms' => 'applied',
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->seller_net_price)->toBeNull()
        ->and($listing->fresh()->seller_price)->toBe(50000);
});

it('stores auction times in UTC from South African input and defaults the end to the auction length', function () {
    $listing = newListing($this->manager);

    $this->actingAs($this->manager)->put(route('admin.listings.update', [$listing, 'auction']), [
        'starts_at' => '2030-03-01T09:00', 'auction_type' => 'sealed',
    ]);

    $listing->refresh();
    expect($listing->starts_at->utc()->format('Y-m-d H:i'))->toBe('2030-03-01 07:00')
        ->and($listing->ends_at->diffInDays($listing->starts_at, true))->toEqual(30.0)
        ->and($listing->auction_type->value)->toBe('sealed');
});

it('cleans descriptions and only accepts YouTube or Vimeo videos', function () {
    $listing = newListing($this->manager);

    $this->actingAs($this->manager)->put(route('admin.listings.update', [$listing, 'details']), [
        'title' => 'Farm', 'description' => '<p onclick="x()">Hi<script>alert(1)</script> <a href="javascript:alert(1)">x</a></p>',
    ]);
    expect($listing->fresh()->description)->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:');

    $this->actingAs($this->manager)->put(route('admin.listings.update', [$listing, 'details']), [
        'title' => 'Farm', 'video_url' => 'https://evil.example.com/video',
    ])->assertSessionHasErrors('video_url');
});

it('validates key facts against their field type', function () {
    $listing = newListing($this->manager);
    $zoning = $this->land->attributeDefinitions()->where('key', 'zoning')->firstOrFail();

    $this->actingAs($this->manager)->put(route('admin.listings.update', [$listing, 'facts']), [
        'facts' => [$zoning->id => 'Lunar'],
    ])->assertSessionHasErrors("facts.{$zoning->id}");
});

it('blocks submission until the listing is complete', function () {
    $listing = newListing($this->manager);

    $this->actingAs($this->manager)->post(route('admin.listings.submit', $listing))->assertSessionHasErrors('listing');
    expect($listing->fresh()->status)->toBe(ListingStatus::Draft);
});

it('requires alt text on every photo before submission', function () {
    $listing = completeListing($this->manager, newListing($this->manager));
    $this->actingAs($this->manager)->post(route('admin.listings.media.store', [$listing, 'photos']), [
        'files' => [UploadedFile::fake()->image('no-alt.jpg', 1200, 800)],
    ]);

    $this->actingAs($this->manager)->post(route('admin.listings.submit', $listing))
        ->assertSessionHasErrors(['listing' => 'Describe every photo (alt text) for screen readers and search engines.']);

    $this->actingAs($this->manager)->put(route('admin.listings.media.update', [$listing, $listing->getMedia('photos')->last()]), ['alt' => ''])
        ->assertSessionHasErrors('alt');
});

it('goes draft → in review → coming soon → live, with history and audit', function () {
    $listing = completeListing($this->manager, newListing($this->manager));

    $this->actingAs($this->manager)->post(route('admin.listings.submit', $listing))->assertSessionHasNoErrors();
    $this->actingAs($this->manager)->post(route('admin.listings.approve', $listing))->assertForbidden();
    $this->actingAs($this->auctioneer)->post(route('admin.listings.approve', $listing))->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Scheduled);

    $this->travel(4)->days();
    $this->artisan('listings:go-live')->assertSuccessful();

    expect($listing->fresh()->status)->toBe(ListingStatus::Live)
        ->and($listing->statusChanges()->count())->toBe(3)
        ->and(AuditEntry::where('event', 'status_changed')->count())->toBe(3);
});

it('sends listings back with a comment and lets the manager edit again', function () {
    $listing = completeListing($this->manager, newListing($this->manager));
    $this->actingAs($this->manager)->post(route('admin.listings.submit', $listing));

    $this->actingAs($this->manager)->put(route('admin.listings.update', [$listing, 'details']), ['title' => 'New'])->assertForbidden();

    $this->actingAs($this->auctioneer)->post(route('admin.listings.request-changes', $listing), ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs($this->auctioneer)->post(route('admin.listings.request-changes', $listing), ['note' => 'Add the water licence.']);

    expect($listing->fresh()->status)->toBe(ListingStatus::ChangesRequested)
        ->and($listing->fresh()->review_note)->toBe('Add the water licence.');
    $this->actingAs($this->manager)->put(route('admin.listings.update', [$listing, 'details']), ['title' => 'Farm portion'])->assertSessionHasNoErrors();
});

it('locks pricing once live except for a super admin giving a reason', function () {
    $listing = completeListing($this->manager, newListing($this->manager));
    $listing->forceFill(['status' => ListingStatus::Live])->save();
    $pricing = ['deal_type' => 'commission', 'seller_net_price' => 1800000, 'fee_percent' => 20, 'minimum_price' => 2400000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied'];

    $this->actingAs($this->auctioneer)->put(route('admin.listings.update', [$listing, 'pricing']), $pricing)->assertForbidden();
    $this->actingAs($this->admin)->put(route('admin.listings.update', [$listing, 'pricing']), $pricing)->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->put(route('admin.listings.update', [$listing, 'pricing']), $pricing + ['reason' => 'Seller agreed a higher minimum'])->assertSessionHasNoErrors();

    expect(AuditEntry::where('event', 'updated')->latest('id')->first()->properties['reason'])->toBe('Seller agreed a higher minimum');
});

it('stores documents privately and only accepts the document type of the category', function () {
    $listing = newListing($this->manager);
    $titleDeed = $this->land->documentTypes()->where('name', 'Title deed')->firstOrFail();
    $miningRight = Category::where('slug', 'mining')->firstOrFail()->documentTypes()->firstOrFail();

    $this->actingAs($this->manager)->post(route('admin.listings.documents.store', [$listing, $titleDeed]), [
        'file' => UploadedFile::fake()->create('deed.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->manager)->post(route('admin.listings.documents.store', [$listing, $miningRight]), [
        'file' => UploadedFile::fake()->create('right.pdf', 100, 'application/pdf'),
    ])->assertNotFound();
    $this->actingAs($this->manager)->post(route('admin.listings.documents.store', [$listing, $titleDeed]), [
        'file' => UploadedFile::fake()->create('deed.exe', 100, 'application/octet-stream'),
    ])->assertSessionHasErrors('file');

    $document = $listing->documents()->firstOrFail();
    Storage::disk('local')->assertExists($document->path);
    Storage::disk('public')->assertMissing($document->path);

    $this->actingAs($this->manager)->get(route('admin.listings.documents.download', [$listing, $document]))->assertOk();
    expect(AuditEntry::where('event', 'document_downloaded')->exists())->toBeTrue();
});

it('only accepts images as photos and keeps photos to their own listing', function () {
    $a = newListing($this->manager);
    $b = newListing($this->manager);

    $this->actingAs($this->manager)->post(route('admin.listings.media.store', [$a, 'photos']), [
        'files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
    ])->assertSessionHasErrors('files.0');

    $this->actingAs($this->manager)->post(route('admin.listings.media.store', [$a, 'photos']), ['files' => [UploadedFile::fake()->image('a.jpg')]]);
    $media = $a->fresh()->getMedia('photos')->first();

    $this->actingAs($this->manager)->delete(route('admin.listings.media.destroy', [$b, $media]))->assertNotFound();
});

it('records compliance sign-offs with a note, by permitted staff only', function () {
    $listing = newListing($this->manager);

    $this->actingAs($this->manager)->post(route('admin.listings.compliance.signoff', [$listing, 'seller_authority']), ['note' => 'Board resolution seen'])->assertForbidden();
    $this->actingAs($this->auctioneer)->post(route('admin.listings.compliance.signoff', [$listing, 'seller_authority']), ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs($this->auctioneer)->post(route('admin.listings.compliance.signoff', [$listing, 'seller_authority']), ['note' => 'Board resolution seen']);
    $this->actingAs($this->auctioneer)->post(route('admin.listings.compliance.signoff', [$listing, 'made_up']), ['note' => 'x'])->assertNotFound();

    expect($listing->complianceItems()->count())->toBe(1)
        ->and(AuditEntry::where('log_name', 'compliance')->count())->toBe(1);

    $this->actingAs($this->auctioneer)->delete(route('admin.listings.compliance.withdraw', [$listing, 'seller_authority']), ['reason' => 'Resolution expired']);
    expect($listing->complianceItems()->count())->toBe(0);
});

it('lets only drafts be deleted', function () {
    $listing = completeListing($this->manager, newListing($this->manager));
    $this->actingAs($this->manager)->post(route('admin.listings.submit', $listing));

    $this->actingAs($this->admin)->delete(route('admin.listings.destroy', $listing))->assertForbidden();
});

it('renders the listings table and the listing form', function () {
    $listing = newListing($this->manager, 'mining');

    $this->actingAs($this->manager)->get(route('admin.listings.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/listings/index')->has('listings.data', 1));

    $this->actingAs($this->manager)->get(route('admin.listings.edit', $listing))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/listings/edit')
            ->where('listing.category.notice', true)
            ->where('listing.depositTerms', 'security')
            ->has('compliance', 5)
            ->where('can.publish', false)
            ->where('can.submit', true));
});

it('hides the listings area from staff without listings permissions', function () {
    $finance = User::factory()->staff(SystemRole::Finance)->create();

    $this->actingAs($finance)->get(route('admin.listings.index'))->assertForbidden();
});
