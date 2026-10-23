<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\DataRoom\Settings\DataRoomSettings;
use App\Domains\DataRoom\Support\Watermarker;
use App\Domains\DataRoom\Support\WatermarkFailed;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Models\ListingDocument;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->app->bind(Watermarker::class, fn () => new Watermarker(compress: false)); // readable stamps in tests
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Naledi', 'last_name' => 'Dube']);
    $this->mine = dataRoomListing();
});

function dataRoomListing(ListingStatus $status = ListingStatus::Live): Listing
{
    static $n = 0;
    $n++;
    $mining = Category::where('slug', 'mining')->firstOrFail();
    $listing = Listing::create([
        'category_id' => $mining->id, 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => "Coal right {$n}", 'slug' => "coal-right-{$n}",
        'deal_type' => 'commission', 'seller_net_price' => 1000000, 'fee_bps' => 2000, 'minimum_price' => 1250000, 'deposit_amount' => 1200000,
        'deposit_terms' => 'security', 'auction_type' => 'open', 'status' => $status, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
    ]);
    foreach (['Mining or prospecting right' => 'xref-stream.pdf', "Seller's authority to sell" => 'plain.pdf'] as $type => $file) {
        $path = "listings/{$listing->ulid}/documents/{$file}";
        Storage::disk('local')->put($path, file_get_contents(base_path("tests/Fixtures/{$file}")));
        $listing->documents()->create(['document_type_id' => $mining->documentTypes()->where('name', $type)->value('id'), 'path' => $path, 'original_name' => $file, 'mime' => 'application/pdf', 'size' => 1200]);
    }
    $homes = Category::where('slug', 'homes')->firstOrFail();
    $path = "listings/{$listing->ulid}/documents/deed.pdf";
    Storage::disk('local')->put($path, file_get_contents(base_path('tests/Fixtures/plain.pdf')));
    $listing->documents()->create(['document_type_id' => $homes->documentTypes()->where('name', 'Title deed')->value('id'), 'path' => $path, 'original_name' => 'deed.pdf', 'mime' => 'application/pdf', 'size' => 1600]);

    return $listing;
}

function verifiedBuyer(User $u): void
{
    Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
    BankAccount::create(['user_id' => $u->id, 'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => '62000000000', 'account_last4' => '0000', 'account_type' => 'cheque', 'holder_name' => 'Naledi Dube', 'status' => 'verified']);
}

function docOf(Listing $l, string $name): ListingDocument
{
    return $l->documents()->where('original_name', $name)->firstOrFail();
}

it('stamps every page with the bidder, including PDFs that need Ghostscript', function () {
    $line = 'Confidential · Naledi Dube · Bidder #4821 · 24 Oct 2026 10:00 SAST · Thabekhulu data room';
    $w = new Watermarker(compress: false);

    $plain = $w->stamp(file_get_contents(base_path('tests/Fixtures/plain.pdf')), 'application/pdf', $line);
    expect(substr_count($plain, 'Bidder #4821'))->toBe(4); // 2 pages x top and bottom

    if (trim((string) shell_exec('command -v gs')) === '') {
        $this->markTestSkipped('Ghostscript not installed here (installed in Docker and CI).');
    }
    $converted = $w->stamp(file_get_contents(base_path('tests/Fixtures/xref-stream.pdf')), 'application/pdf', $line);
    expect($converted)->toContain('Bidder #4821');
});

it('refuses rather than serving a document it cannot stamp', function () {
    expect(fn () => (new Watermarker)->stamp('%PDF-1.4 not really a pdf', 'application/pdf', 'x'))->toThrow(WatermarkFailed::class)
        ->and(fn () => (new Watermarker)->stamp('x', 'application/zip', 'x'))->toThrow(WatermarkFailed::class);
});

it('opens verified-bidder documents to verified buyers, never internal ones', function () {
    $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, docOf($this->mine, 'deed.pdf')]))->assertForbidden();

    verifiedBuyer($this->buyer);
    $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, docOf($this->mine, 'deed.pdf')]))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, docOf($this->mine, 'plain.pdf')]))->assertForbidden(); // internal
});

it('opens data-room documents only to approved bidders who signed the current NDA', function () {
    verifiedBuyer($this->buyer);
    $doc = docOf($this->mine, 'xref-stream.pdf');

    $this->actingAs($this->buyer)->post(route('dataroom.nda', $this->mine->ulid), ['typed_name' => 'Naledi Dube', 'agree' => '1'])->assertForbidden(); // hasn't applied

    $approval = BidderApproval::create(['listing_id' => $this->mine->id, 'user_id' => $this->buyer->id, 'status' => 'pending']);
    $this->actingAs($this->buyer)->post(route('dataroom.nda', $this->mine->ulid), ['typed_name' => 'Naledi Dube'])->assertSessionHasErrors('agree');
    $this->actingAs($this->buyer)->post(route('dataroom.nda', $this->mine->ulid), ['typed_name' => 'Naledi Dube', 'agree' => '1'])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, $doc]))->assertForbidden(); // still pending

    $approval->update(['status' => 'approved', 'bidder_number' => '4821']);
    if (trim((string) shell_exec('command -v gs')) === '') {
        $this->markTestSkipped('Ghostscript not installed here.');
    }
    $file = $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, $doc]))->assertOk()->getContent();
    expect($file)->toContain('Naledi Dube')->toContain('Bidder #4821');

    expect(DB::table('document_downloads')->where('user_id', $this->buyer->id)->value('bidder_number'))->toBe('4821');

    $settings = app(DataRoomSettings::class);
    $settings->nda_version = 2;
    $settings->save();
    $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, $doc]))->assertForbidden(); // must accept v2
});

it('does not open one listing’s data room through another listing’s approval', function () {
    verifiedBuyer($this->buyer);
    $other = dataRoomListing();
    BidderApproval::create(['listing_id' => $other->id, 'user_id' => $this->buyer->id, 'status' => 'approved', 'bidder_number' => '1111']);
    DB::table('nda_acceptances')->insert(['listing_id' => $other->id, 'user_id' => $this->buyer->id, 'nda_version' => 1, 'typed_name' => 'N', 'accepted_at' => now()]);

    $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, docOf($this->mine, 'xref-stream.pdf')]))->assertForbidden();
    $this->actingAs($this->buyer)->get(route('dataroom.download', [$other->ulid, docOf($this->mine, 'xref-stream.pdf')]))->assertNotFound();
});

it('closes documents on listings that are not public', function () {
    verifiedBuyer($this->buyer);
    $draft = dataRoomListing(ListingStatus::Draft);

    $this->actingAs($this->buyer)->get(route('dataroom.download', [$draft->ulid, docOf($draft, 'deed.pdf')]))->assertForbidden();
});

it('refuses and reports a download that cannot be stamped', function () {
    verifiedBuyer($this->buyer);
    $deed = docOf($this->mine, 'deed.pdf');
    Storage::disk('local')->put($deed->path, 'corrupt');

    $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, $deed]))->assertStatus(503);
    expect(DB::table('document_downloads')->count())->toBe(0)
        ->and(AuditEntry::where('event', 'watermark_failed')->exists())->toBeTrue();
});

it('shows staff the download log and flags heavy downloading', function () {
    verifiedBuyer($this->buyer);
    $settings = app(DataRoomSettings::class);
    $settings->daily_download_alert = 2;
    $settings->save();
    foreach (range(1, 3) as $i) {
        $this->actingAs($this->buyer)->get(route('dataroom.download', [$this->mine->ulid, docOf($this->mine, 'deed.pdf')]));
    }
    $auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create();

    $this->actingAs($auctioneer)->get(route('admin.listings.bidders', $this->mine))->assertInertia(fn (Assert $p) => $p
        ->has('downloads.rows', 3)->where('downloads.flagged', ['Naledi Dube']));
});

it('publishes a new NDA version only when the text changes', function () {
    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $text = app(DataRoomSettings::class)->nda_text;

    $this->actingAs($admin)->put(route('admin.dataroom.settings.update'), ['nda_text' => $text, 'daily_download_alert' => 30]);
    expect(app(DataRoomSettings::class)->nda_version)->toBe(1);

    $this->actingAs($admin)->put(route('admin.dataroom.settings.update'), ['nda_text' => '<p>Final attorney text.</p><script>x</script>', 'daily_download_alert' => 30]);
    expect(app(DataRoomSettings::class)->nda_version)->toBe(2)->and(app(DataRoomSettings::class)->nda_text)->not->toContain('script');
});

it('builds the buyer dashboard with the next steps', function () {
    $this->actingAs($this->buyer)->get(route('account'))->assertInertia(fn (Assert $p) => $p
        ->component('account/dashboard')->where('attention.0.kind', 'verify')->where('status.verification', 'Not started'));

    verifiedBuyer($this->buyer);
    BidderApproval::create(['listing_id' => $this->mine->id, 'user_id' => $this->buyer->id, 'status' => 'approved', 'bidder_number' => '4821']);

    $this->actingAs($this->buyer)->get(route('account'))->assertInertia(fn (Assert $p) => $p
        ->where('stats.approved', 1)->where('auctions.0.bidderNumber', '4821')
        ->where('attention', fn ($a) => collect($a)->pluck('kind')->contains('nda')));
});
