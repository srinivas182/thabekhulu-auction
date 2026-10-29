<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Actions\Relist;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\Refund;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Domains\Settlement\Actions\PayoutService;
use App\Domains\Settlement\Actions\SettlementService;
use App\Domains\Settlement\Models\Invoice;
use App\Domains\Settlement\Models\Payout;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Settlement\Models\Suspension;
use App\Domains\Settlement\Notifications\SettlementNotice;
use App\Domains\Settlement\Notifications\SettlementQuestion;
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
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create();
    $this->finance = User::factory()->staff(SystemRole::Finance)->create();
});

/** A closed auction: A wins at R1 200 000, B is runner-up at R1 100 000, C lost. Deposits R50 000 each. */
function settledAuction(string $terms = 'applied', string $category = 'homes'): array
{
    $l = Listing::create([
        'category_id' => Category::where('slug', $category)->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => 'Family home', 'slug' => 'family-home-'.uniqid(),
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => $terms,
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addHour(),
    ]);
    $users = [];
    foreach (['A' => '1111', 'B' => '2222', 'C' => '3333'] as $name => $number) {
        $u = User::factory()->create(['type' => 'buyer', 'first_name' => $name, 'last_name' => 'Buyer']);
        $ap = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => $number]);
        Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
        Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
        BankAccount::create(['user_id' => $u->id, 'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => (string) random_int(6200000000, 6299999999), 'account_last4' => '0000', 'account_type' => 'cheque', 'holder_name' => $name, 'status' => 'verified']);
        $d = Deposit::create(['listing_id' => $l->id, 'user_id' => $u->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make($number), 'status' => 'awaiting']);
        app(DepositService::class)->receive($d, 5000000, 'bank_import', "seed {$number}");
        $users[$name] = $u;
    }
    $e = app(BidEngine::class);
    $e->place($l, $users['C'], 1000000);
    $e->place($l, $users['B'], 1100000);
    $e->place($l, $users['A'], 1200000);
    $l->update(['ends_at' => now()->subSecond()]);
    AuctionState::query()->where('listing_id', $l->id)->update(['ends_at' => now()->subSecond()]);
    test()->artisan('auctions:close');

    return [$l->fresh(), $users, SettlementCase::where('listing_id', $l->id)->firstOrFail()];
}

function depositOf(User $u): Deposit
{
    return Deposit::where('user_id', $u->id)->firstOrFail();
}

it('opens the winner’s settlement at close with deadlines from settings', function () {
    [, $u, $case] = settledAuction();

    expect($case->kind)->toBe('sale')->and($case->status)->toBe('open')->and($case->price)->toBe(1200000)
        ->and($case->approval->user_id)->toBe($u['A']->id)
        ->and($case->balance_due->toDateString())->toBe(today()->addDays(30)->toDateString())
        ->and($case->agreement_due->isWeekend())->toBeFalse();
});

it('completes only with every step, applying an applied deposit to the price', function () {
    [, $u, $case] = settledAuction('applied');
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.complete', $case))->assertSessionHasErrors('case');

    foreach (array_keys(SettlementCase::STEPS) as $step) {
        $this->actingAs($this->auctioneer)->post(route('admin.settlement.step', $case), ['step' => $step, 'done_on' => today()->toDateString()])->assertSessionHasNoErrors();
    }
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.complete', $case))->assertSessionHasNoErrors();

    expect($case->fresh()->status)->toBe('completed')->and(depositOf($u['A'])->status)->toBe('applied')->and(depositOf($u['A'])->balance())->toBe(0)
        ->and(LedgerEntry::where('type', 'applied')->sum('amount'))->toEqual(-5000000);
});

it('releases a security deposit for refund at completion', function () {
    [, $u, $case] = settledAuction('security');
    foreach (array_keys(SettlementCase::STEPS) as $step) {
        app(SettlementService::class)->recordStep($case, $step, today()->toDateString(), null, null, $this->auctioneer);
    }
    app(SettlementService::class)->complete($case, $this->auctioneer);

    expect(Refund::where('deposit_id', depositOf($u['A'])->id)->value('reason'))->toBe('security_released');
});

it('caps the forfeiture at the lowest of 10% of the price, the auction cost and the deposit held', function () {
    expect(SettlementService::cap(120000000, 3000000, 5000000))->toBe(3000000)    // auction cost lowest
        ->and(SettlementService::cap(120000000, 99000000, 5000000))->toBe(5000000) // deposit held lowest
        ->and(SettlementService::cap(30000000, 99000000, 5000000))->toBe(3000000)  // 10% of R300 000 lowest
        ->and(SettlementService::cap(120000000, 0, 5000000))->toBe(0);
});

it('runs a default: missed deadline, two people, capped forfeit, excess refund, suspension, runner-up offer', function () {
    [$l, $u, $case] = settledAuction();
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.default', $case), ['reason' => 'No signature', 'auction_cost' => 30000])->assertSessionHasErrors('default'); // nothing missed yet

    $this->travelTo($case->agreement_due->addDay());
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.default', $case), ['reason' => 'No signature', 'auction_cost' => 30000])->assertSessionHasNoErrors();
    expect($case->fresh()->default->proposed_forfeit)->toBe(3000000);

    $this->actingAs($this->auctioneer)->post(route('admin.settlement.default.confirm', $case))->assertForbidden(); // auctioneers can't confirm
    $this->actingAs($this->finance)->post(route('admin.settlement.default.confirm', $case))->assertSessionHasNoErrors();

    $a = depositOf($u['A']);
    expect(LedgerEntry::where('deposit_id', $a->id)->where('type', 'forfeited')->sum('amount'))->toEqual(-3000000)
        ->and($a->balance())->toBe(2000000)
        ->and(Refund::where('deposit_id', $a->id)->value('reason'))->toBe('default_excess')
        ->and($case->fresh()->status)->toBe('defaulted')
        ->and(Suspension::activeFor($u['A']->id)?->until->toDateString())->toBe(today()->addMonths(12)->toDateString());

    $offer = SettlementCase::where('listing_id', $l->id)->where('kind', 'runner_up')->firstOrFail();
    expect($offer->status)->toBe('offered')->and($offer->price)->toBe(1100000)->and($offer->approval->user_id)->toBe($u['B']->id);
    Notification::assertSentTo($u['B'], SettlementNotice::class, fn ($n) => $n->kind === 'offer');
});

it('needs the security sign-off and a second person', function () {
    [, , $case] = settledAuction('security', 'mining');
    $this->travelTo($case->agreement_due->addDay());
    $both = User::factory()->staff(SystemRole::SuperAdmin)->create();

    $this->actingAs($both)->post(route('admin.settlement.default', $case), ['reason' => 'x', 'auction_cost' => 30000])->assertSessionHasErrors('security_signoff');
    $this->actingAs($both)->post(route('admin.settlement.default', $case), ['reason' => 'x', 'auction_cost' => 30000, 'security_signoff' => true])->assertSessionHasNoErrors();
    $this->actingAs($both)->post(route('admin.settlement.default.confirm', $case))->assertSessionHasErrors('default'); // declarer can't confirm
});

it('blocks a suspended buyer from applying and bidding', function () {
    [$l, $u] = settledAuction();
    Suspension::create(['user_id' => $u['C']->id, 'until' => today()->addMonths(12), 'reason' => 'Default']);
    $other = Listing::create(['category_id' => $l->category_id, 'city_id' => $l->city_id, 'title' => 'Other', 'slug' => 'other-'.uniqid(), 'deal_type' => 'commission',
        'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied', 'auction_type' => 'open',
        'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);

    $this->actingAs($u['C'])->post(route('auctions.apply', $other->ulid))->assertSessionHasErrors('apply');
    BidderApproval::create(['listing_id' => $other->id, 'user_id' => $u['C']->id, 'status' => 'approved', 'bidder_number' => '9999']);
    expect(fn () => app(BidEngine::class)->place($other, $u['C'], 1000000))->toThrow(ValidationException::class);
});

it('lets the runner-up accept (refund cancelled) or lets the offer expire', function () {
    [$l, $u, $case] = settledAuction();
    $this->travelTo($case->agreement_due->addDay());
    app(SettlementService::class)->declareDefault($case, 'No signature', 3000000, false, $this->auctioneer);
    app(SettlementService::class)->confirmDefault($case->fresh(), $this->finance);
    $offer = SettlementCase::where('kind', 'runner_up')->firstOrFail();

    $this->actingAs($u['C'])->post(route('account.purchases.accept', $offer))->assertForbidden();
    $this->actingAs($u['B'])->post(route('account.purchases.accept', $offer))->assertSessionHasNoErrors();

    expect($offer->fresh()->status)->toBe('open')->and($offer->fresh()->agreement_due)->not->toBeNull()
        ->and(depositOf($u['B'])->status)->toBe('received')
        ->and(Refund::where('deposit_id', depositOf($u['B'])->id)->where('status', 'scheduled')->exists())->toBeFalse();
});

it('expires an unanswered runner-up offer and reminds buyers of deadlines', function () {
    [, $u, $case] = settledAuction();
    $this->travelTo($case->agreement_due->subDays(2)->setTime(8, 0));
    $this->artisan('settlement:daily');
    Notification::assertSentTo($u['A'], SettlementNotice::class, fn ($n) => $n->kind === 'reminder');

    $this->travelTo($case->agreement_due->addDay());
    app(SettlementService::class)->declareDefault($case, 'x', 3000000, false, $this->auctioneer);
    app(SettlementService::class)->confirmDefault($case->fresh(), $this->finance);
    $this->travel(6)->days();
    $this->artisan('settlement:daily');

    expect(SettlementCase::where('kind', 'runner_up')->value('status'))->toBe('expired');
});

it('labels a sold Mining listing as preferred bidder, subject to approvals', function () {
    [$l] = settledAuction('security', 'mining');

    $this->get("/auctions/mining/emalahleni/{$l->slug}")->assertInertia(fn (Assert $p) => $p
        ->where('listing.status.label', 'Preferred bidder: subject to approvals')->where('auction.mining', true)->where('auction.outcome', 'under_offer'));
});

it('limits settlement pages to staff', function () {
    [, $u, $case] = settledAuction();

    $this->actingAs($u['A'])->get(route('admin.settlement.show', $case))->assertForbidden();
    $this->actingAs($this->finance)->get(route('admin.settlement.show', $case))->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.confirm', true)->where('can.declare', false));
    $this->actingAs($u['A'])->get(route('account.purchases'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('cases', 1));
});

/** Records every step and completes the case. */
function completeCase(SettlementCase $case, User $by): SettlementCase
{
    foreach (array_keys(SettlementCase::STEPS) as $step) {
        app(SettlementService::class)->recordStep($case, $step, today()->toDateString(), null, null, $by);
    }
    app(SettlementService::class)->complete($case->fresh(), $by);

    return $case->fresh();
}

it('computes the seller payout in cents for both deal types', function () {
    [$l, , $case] = settledAuction();
    $f = PayoutService::figures($case);
    // R1 200 000, 20% fee = R240 000, VAT 15% on the fee = R36 000, seller receives R924 000.
    expect($f)->toMatchArray(['deal' => 'commission', 'price' => 120000000, 'fee' => 24000000, 'vat' => 3600000, 'amount' => 92400000]);

    $l->update(['deal_type' => 'renovated', 'seller_price' => 900000]);
    expect(PayoutService::figures($case->fresh())['amount'])->toBe(90000000)
        ->and(PayoutService::figures($case->fresh())['fee'])->toBe(0);
});

it('pays the seller only after completion, with two finance users and proof', function () {
    [, , $case] = settledAuction();
    $finance2 = User::factory()->staff(SystemRole::Finance)->create();
    $this->actingAs($this->finance)->post(route('admin.settlement.payout', $case), ['payee' => 'Seller (Pty) Ltd'])->assertSessionHasErrors('payout'); // not completed

    $case = completeCase($case, $this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.payout', $case), ['payee' => 'x'])->assertForbidden();
    $this->actingAs($this->finance)->post(route('admin.settlement.payout', $case), ['payee' => 'Seller (Pty) Ltd', 'payee_reference' => 'FNB 62xxxx'])->assertSessionHasNoErrors();
    $payout = Payout::firstOrFail();
    expect($payout->amount)->toBe(92400000);

    $this->actingAs($this->finance)->post(route('admin.settlement.payout.approve', $payout))->assertSessionHasErrors('payout'); // own payout
    $this->actingAs($finance2)->post(route('admin.settlement.payout.approve', $payout))->assertSessionHasNoErrors();
    $this->actingAs($this->finance)->post(route('admin.settlement.payout.paid', $payout))->assertSessionHasErrors('proof');
    $this->actingAs($this->finance)->post(route('admin.settlement.payout.paid', $payout), ['proof' => UploadedFile::fake()->create('pop.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();

    expect($payout->fresh()->status)->toBe('paid')->and($payout->fresh()->approved_by)->toBe($finance2->id);
});

it('issues gap-free VAT invoices for commission sales only', function () {
    [, , $case1] = settledAuction();
    [$l2, , $case2] = settledAuction();
    $case1 = completeCase($case1, $this->auctioneer);
    $case2 = completeCase($case2, $this->auctioneer);
    $year = now('Africa/Johannesburg')->format('Y');

    $this->actingAs($this->finance)->post(route('admin.settlement.invoice', $case1), ['recipient' => 'Seller One', 'recipient_vat_number' => '123'])->assertSessionHasErrors('recipient_vat_number');
    $this->actingAs($this->finance)->post(route('admin.settlement.invoice', $case1), ['recipient' => 'Seller One', 'recipient_vat_number' => '4123456789'])->assertSessionHasNoErrors();
    $this->actingAs($this->finance)->post(route('admin.settlement.invoice', $case1), ['recipient' => 'Seller One'])->assertSessionHasErrors('invoice'); // one per sale
    $this->actingAs($this->finance)->post(route('admin.settlement.invoice', $case2), ['recipient' => 'Seller Two'])->assertSessionHasNoErrors();

    $invoices = Invoice::orderBy('id')->get();
    expect($invoices->pluck('number')->all())->toBe(["TDG-INV-{$year}-0001", "TDG-INV-{$year}-0002"])
        ->and($invoices[0]->fee)->toBe(24000000)->and($invoices[0]->vat)->toBe(3600000)->and($invoices[0]->total)->toBe(27600000);
    $this->actingAs($this->finance)->get(route('admin.settlement.invoice.pdf', $invoices[0]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $html = view('pdf.invoice', ['invoice' => $invoices[0], 'company' => app(QuotationSettings::class), 'tz' => 'Africa/Johannesburg'])->render();
    expect($html)->toContain('Tax invoice')->toContain('R 240 000.00')->toContain('VAT at 15%')->toContain('R 276 000.00');

    [$l3, , $case3] = settledAuction();
    $l3->update(['deal_type' => 'renovated', 'seller_price' => 900000]);
    $case3 = completeCase($case3->fresh(), $this->auctioneer);
    $this->actingAs($this->finance)->post(route('admin.settlement.invoice', $case3), ['recipient' => 'x'])->assertSessionHasErrors('invoice');
});

it('tracks transfer stages per listing type and shows them to the buyer', function () {
    [, $u, $case] = settledAuction();
    $case = completeCase($case, $this->auctioneer);

    $this->actingAs($this->auctioneer)->post(route('admin.settlement.transfer', $case), ['stage' => 's11_applied', 'done_on' => today()->toDateString()])->assertSessionHasErrors('stage');
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.transfer', $case), ['stage' => 'lodged', 'done_on' => today()->toDateString()])->assertSessionHasNoErrors();

    $this->actingAs($u['A'])->get(route('account.purchases'))->assertInertia(fn (Assert $p) => $p
        ->where('cases.0.transfer.0.key', 'lodged')->where('cases.0.transfer.0.doneOn', today()->format('j F Y'))->where('cases.0.transfer.1.doneOn', null));

    [, , $mine] = settledAuction('security', 'mining');
    $mine = completeCase($mine, $this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.transfer', $mine), ['stage' => 's11_applied', 'done_on' => today()->toDateString()])->assertSessionHasNoErrors();
});

it('shows a winning bid as under offer, and sold only once settlement completes', function () {
    [$l, , $case] = settledAuction('applied');
    expect($l->status)->toBe(ListingStatus::UnderOffer);
    $this->get("/auctions/homes/emalahleni/{$l->slug}")->assertOk()->assertInertia(fn (Assert $p) => $p->where('auction.outcome', 'under_offer')->where('auction.result', null));

    foreach (array_keys(SettlementCase::STEPS) as $step) {
        $this->actingAs($this->auctioneer)->post(route('admin.settlement.step', $case), ['step' => $step, 'done_on' => today()->toDateString()])->assertSessionHasNoErrors();
    }
    expect($l->fresh()->status)->toBe(ListingStatus::UnderOffer); // steps alone don't make it sold
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.complete', $case))->assertSessionHasNoErrors();

    expect($l->fresh()->status)->toBe(ListingStatus::Sold);
    $this->get("/auctions/homes/emalahleni/{$l->slug}")->assertInertia(fn (Assert $p) => $p->where('auction.outcome', 'sold')->where('auction.result.price', 1200000));
});

it('marks the listing not sold when the runner-up declines, and lets it be relisted', function () {
    [$l, $u] = settledAuction('applied');
    $offer = app(SettlementService::class)->offerRunnerUp($l);
    expect($offer)->not->toBeNull()->and($l->fresh()->status)->toBe(ListingStatus::UnderOffer); // still under offer while the runner-up decides

    app(SettlementService::class)->declineOffer($offer, $u['B']);
    expect($l->fresh()->status)->toBe(ListingStatus::NotSold)
        ->and($l->fresh()->status->isShown())->toBeFalse();                       // hidden from the public, like no bids
    app(Relist::class)->handle($l->fresh(), $this->auctioneer);
    expect($l->fresh()->status)->not->toBe(ListingStatus::NotSold);
});

it('marks the listing not sold when there is no runner-up, or the offer lapses', function () {
    [$l] = settledAuction('applied');
    DB::table('auction_results')->where('listing_id', $l->id)->update(['runner_up_approval_id' => null, 'runner_up_amount' => null]);
    expect(app(SettlementService::class)->offerRunnerUp($l))->toBeNull()
        ->and($l->fresh()->status)->toBe(ListingStatus::NotSold);

    [$l2] = settledAuction('applied');
    $offer = app(SettlementService::class)->offerRunnerUp($l2);
    $offer->forceFill(['offer_expires_at' => now()->subMinute()])->save();
    $this->artisan('settlement:daily')->assertSuccessful();
    expect($l2->fresh()->status)->toBe(ListingStatus::NotSold);
});

it('lets staff ask the buyer a question and the buyer reply, without rejecting the upload', function () {
    [, $u, $case] = settledAuction('applied');
    $this->actingAs($u['A'])->post(route('account.purchases.agreement', $case), ['document' => UploadedFile::fake()->create('agreement.pdf', 80, 'application/pdf')])->assertSessionHasNoErrors();
    $upload = (int) DB::table('settlement_uploads')->where('settlement_case_id', $case->id)->value('id');

    $this->actingAs($this->auctioneer)->post(route('admin.settlement.questions', $case), ['question' => 'Page 3 is not initialled. Please initial it.', 'upload' => $upload])->assertSessionHasNoErrors();
    Notification::assertSentTo($u['A'], SettlementQuestion::class);
    expect($case->fresh()->hasOpenQuestion())->toBeTrue()
        ->and(DB::table('settlement_uploads')->where('id', $upload)->value('status'))->toBe('pending'); // asking doesn't reject

    $this->actingAs($u['A'])->get(route('account.purchases'))->assertInertia(fn (Assert $p) => $p->where('cases.0.openQuestion', true)->where('cases.0.messages.1.body', 'Page 3 is not initialled. Please initial it.'));
    $this->actingAs($u['A'])->post(route('account.purchases.reply', $case), ['reply' => 'Initialled and uploaded again.'])->assertSessionHasNoErrors();
    expect($case->fresh()->hasOpenQuestion())->toBeFalse();

    $this->actingAs($this->auctioneer)->post(route('admin.settlement-uploads.accept', $upload))->assertSessionHasNoErrors();
    expect($case->fresh()->messages()->pluck('kind')->all())->toBe(['upload', 'question', 'reply', 'accepted'])
        ->and($case->fresh()->messages()->where('kind', 'question')->value('settlement_upload_id'))->toBe($upload);
    $this->actingAs($this->auctioneer)->get(route('admin.settlement.show', $case))->assertInertia(fn (Assert $p) => $p->has('case.messages', 4)->where('case.openQuestion', false));
});

it('keeps the conversation to the buyer and settlement staff', function () {
    [, $u, $case] = settledAuction('applied');
    $this->actingAs($u['B'])->post(route('account.purchases.reply', $case), ['reply' => 'Not mine'])->assertForbidden();
    $this->actingAs(User::factory()->staff(SystemRole::Support)->create())->post(route('admin.settlement.questions', $case), ['question' => 'Hello?'])->assertForbidden();
});
