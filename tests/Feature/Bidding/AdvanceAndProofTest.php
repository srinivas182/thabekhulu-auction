<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Bidding\Actions\AdvanceBids;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\AdvanceBid;
use App\Domains\Bidding\Notifications\BidCode;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Actions\ListingWorkflow;
use App\Domains\Listings\Enums\ComplianceItem;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Notifications\DepositsOpen;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->listing = Listing::create([
        'category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'sandton')->value('id'), 'title' => 'Family home', 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Scheduled, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(5),
    ]);
});

/** Approved for the test listing, verified and screened; deposit received unless told otherwise. */
function advBidder(string $name, string $number, ?string $deposit = 'received'): User
{
    $u = User::factory()->create(['type' => 'buyer', 'first_name' => $name]);
    $a = BidderApproval::create(['listing_id' => test()->listing->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => $number]);
    Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
    if ($deposit !== null) {
        Deposit::create(['listing_id' => test()->listing->id, 'user_id' => $u->id, 'bidder_approval_id' => $a->id, 'amount' => 5000000, 'reference' => DepositReference::make($number), 'status' => $deposit]);
    }

    return $u;
}

it('opens a deposit only once the buyer is approved', function () {
    $u = User::factory()->create(['type' => 'buyer']);
    $pending = BidderApproval::create(['listing_id' => $this->listing->id, 'user_id' => $u->id, 'status' => 'pending']);

    $message = function (BidderApproval $a) use ($u): ?string {
        try {
            app(DepositService::class)->open($a, $u);

            return null;
        } catch (ValidationException $e) {
            return $e->errors()['deposit'][0] ?? null;
        }
    };
    expect($message($pending))->toBe('You can pay the deposit once you’re approved to bid.');
    $pending->forceFill(['status' => 'approved', 'bidder_number' => '4444'])->save();
    expect($message($pending->fresh()))->not->toBe('You can pay the deposit once you’re approved to bid.'); // other checks (listing sign-off) may still apply
});

it('needs a received deposit, the minimum, and a listing not yet live for an advance maximum', function () {
    $unpaid = advBidder('Unpaid', '1111', 'awaiting');
    $paid = advBidder('Paid', '2222');
    $advance = app(AdvanceBids::class);

    expect(fn () => $advance->set($this->listing, $unpaid, 1500000))->toThrow(ValidationException::class)
        ->and(fn () => $advance->set($this->listing, $paid, 900000))->toThrow(ValidationException::class);

    // Large amounts need the emailed code, exactly like live bids.
    $this->actingAs($paid)->post(route('auctions.advance', $this->listing->ulid), ['maximum' => 1500000])->assertSessionHasErrors('otp');
    $code = null;
    Notification::assertSentTo($paid, BidCode::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });
    $this->actingAs($paid)->post(route('auctions.advance', $this->listing->ulid), ['maximum' => 1500000, 'otp' => $code])->assertSessionHasNoErrors();
    expect((int) AdvanceBid::firstOrFail()->amount)->toBe(1500000);

    $this->listing->forceFill(['status' => ListingStatus::Live])->save();
    expect(fn () => $advance->set($this->listing->fresh(), $paid, 1600000))->toThrow(ValidationException::class);
});

it('places advance maximums through the bid engine when bidding opens', function () {
    $high = advBidder('High', '1111');
    $low = advBidder('Low', '2222');
    $advance = app(AdvanceBids::class);
    $advance->set($this->listing, $high, 1500000);
    $advance->set($this->listing, $low, 1300000);

    $this->listing->forceFill(['starts_at' => now()->subMinute()])->save();
    app(ListingWorkflow::class)->goLive($this->listing->fresh());

    $state = BidEngine::state($this->listing->fresh());
    $highApproval = BidderApproval::where('user_id', $high->id)->firstOrFail();
    expect($this->listing->fresh()->status)->toBe(ListingStatus::Live)
        ->and($state->leader_approval_id)->toBe($highApproval->id)
        ->and($state->current_amount)->toBeGreaterThan(1300000)->toBeLessThanOrEqual(1500000)  // just above the other maximum, not the full ceiling
        ->and(AdvanceBid::whereNull('applied_at')->count())->toBe(0);
});

it('gives equal advance maximums to the one set first', function () {
    $first = advBidder('First', '1111');
    $second = advBidder('Second', '2222');
    $advance = app(AdvanceBids::class);
    $advance->set($this->listing, $first, 1400000);
    $this->travel(1)->seconds();
    $advance->set($this->listing, $second, 1400000);

    $this->listing->forceFill(['starts_at' => now()->subMinute()])->save();
    app(ListingWorkflow::class)->goLive($this->listing->fresh());

    expect(BidEngine::state($this->listing->fresh())->leader_approval_id)->toBe(BidderApproval::where('user_id', $first->id)->value('id'));
});

it('takes proof of an EFT payment and lets finance open it, logged', function () {
    $buyer = advBidder('Eft', '5555', 'awaiting');
    $this->actingAs($buyer)->post(route('deposits.proof', $this->listing), ['proof' => UploadedFile::fake()->create('pop.pdf', 120, 'application/pdf')])->assertSessionHasNoErrors();

    $deposit = Deposit::where('user_id', $buyer->id)->firstOrFail();
    expect($deposit->proof_path)->not->toBeNull()->and($deposit->status)->toBe('awaiting'); // proof alone never confirms

    $this->actingAs(User::factory()->staff(SystemRole::Finance)->create())->get(route('admin.deposits.proof', $deposit))->assertOk();
    expect(AuditEntry::where('event', 'deposit_proof_viewed')->exists())->toBeTrue();
    $this->actingAs(User::factory()->staff(SystemRole::ListingsManager)->create())->get(route('admin.deposits.proof', $deposit))->assertForbidden();
});

it('emails approved, unpaid bidders once, when the last legal check is signed off', function () {
    $unpaid = advBidder('Unpaid', '1111', null);
    $paid = advBidder('Paid', '2222');
    $pendingUser = User::factory()->create(['type' => 'buyer']);
    BidderApproval::create(['listing_id' => $this->listing->id, 'user_id' => $pendingUser->id, 'status' => 'pending']);
    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $items = ComplianceItem::cases();

    $this->actingAs($unpaid)->get("/auctions/homes/sandton/{$this->listing->slug}")->assertInertia(fn ($p) => $p->where('bidder.depositsOpen', false));
    foreach (array_slice($items, 0, -1) as $item) {
        $this->actingAs($admin)->post(route('admin.listings.compliance.signoff', [$this->listing, $item->value]), ['note' => 'Checked'])->assertSessionHasNoErrors();
    }
    Notification::assertNothingSent();
    $this->actingAs($admin)->get(route('admin.bidders.queue'))->assertInertia(fn ($p) => $p->where('applications.0.checksSigned', count($items) - 1)->where('applications.0.checksTotal', count($items)));

    $this->actingAs($admin)->post(route('admin.listings.compliance.signoff', [$this->listing, end($items)->value]), ['note' => 'Checked'])->assertSessionHasNoErrors();
    Notification::assertSentTo($unpaid, DepositsOpen::class);
    Notification::assertNotSentTo([$paid, $pendingUser], DepositsOpen::class);

    $this->actingAs($admin)->post(route('admin.listings.compliance.signoff', [$this->listing, $items[0]->value]), ['note' => 'Re-checked'])->assertSessionHasNoErrors();
    Notification::assertSentToTimes($unpaid, DepositsOpen::class, 1);
    $this->actingAs($unpaid)->get("/auctions/homes/sandton/{$this->listing->slug}")->assertInertia(fn ($p) => $p->where('bidder.depositsOpen', true));
});

it('attaches evidence to a compliance sign-off, kept on record and opened only by compliance staff', function () {
    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $item = ComplianceItem::cases()[0]->value;
    $route = route('admin.listings.compliance.signoff', [$this->listing, $item]);

    $this->actingAs($admin)->post($route, ['note' => 'Mandate checked', 'files' => [UploadedFile::fake()->create('virus.exe', 10)]])->assertSessionHasErrors('files.0');
    $this->actingAs($admin)->post($route, ['note' => 'Mandate checked', 'files' => array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 5, 'application/pdf'), range(1, 11))])->assertSessionHasErrors('files');
    $this->actingAs($admin)->post($route, ['note' => 'Mandate checked', 'files' => [
        UploadedFile::fake()->create('mandate.pdf', 80, 'application/pdf'), UploadedFile::fake()->image('title-deed.jpg'),
    ]])->assertSessionHasNoErrors();

    $files = DB::table('listing_compliance_attachments')->where('listing_id', $this->listing->id)->orderBy('id')->get();
    expect($files)->toHaveCount(2)->and($files[0]->name)->toBe('mandate.pdf');
    $this->actingAs($admin)->get(route('admin.listings.edit', $this->listing))->assertInertia(fn ($p) => $p->where('compliance.0.attachments.1.name', 'title-deed.jpg'));

    $this->actingAs($admin)->get(route('admin.listings.compliance.file', [$this->listing, $files[0]->id]))->assertOk();
    expect(AuditEntry::where('event', 'attachment_viewed')->exists())->toBeTrue();
    $outsider = User::factory()->staff(SystemRole::Support)->create();
    $outsider->syncRoles([]);
    $outsider->syncPermissions(['listings.view']);
    $this->actingAs($outsider)->get(route('admin.listings.compliance.file', [$this->listing, $files[0]->id]))->assertForbidden();

    $this->actingAs($admin)->delete(route('admin.listings.compliance.withdraw', [$this->listing, $item]), ['reason' => 'Mandate expired'])->assertSessionHasNoErrors();
    expect(DB::table('listing_compliance_attachments')->where('listing_id', $this->listing->id)->count())->toBe(2); // kept on record
});

it('answers an upload bigger than PHP allows with a clear message, not an error page', function () {
    $buyer = advBidder('Big', '6666', 'awaiting');
    $this->actingAs($buyer)->from(route('deposits.show', $this->listing))
        ->call('POST', route('deposits.proof', $this->listing), [], [], [], ['CONTENT_LENGTH' => 999_999_999_999, 'HTTP_X_INERTIA' => 'true'])
        ->assertStatus(303);
});

it('shows the refund bank and ready-to-bid steps from the latest bank account', function () {
    $buyer = advBidder('Steps', '7777');
    BankAccount::create(['user_id' => $buyer->id, 'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => '62000000001', 'account_last4' => '0001',
        'account_type' => 'cheque', 'holder_name' => 'Steps', 'status' => 'superseded']);
    BankAccount::create(['user_id' => $buyer->id, 'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => '62000000002', 'account_last4' => '0002',
        'account_type' => 'cheque', 'holder_name' => 'Steps', 'status' => 'verified']);

    $this->actingAs($buyer)->get(route('account.verification'))->assertInertia(fn ($p) => $p->where('bankStatus', 'verified')->where('verification.status', 'verified'));
});

it('reports pending database updates in system status', function () {
    $this->actingAs(User::factory()->staff(SystemRole::SuperAdmin)->create())->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p->where('health.migrations.ok', true)->has('health.uploads.detail'));
});
