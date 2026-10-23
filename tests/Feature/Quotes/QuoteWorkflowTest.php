<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Quotes\Models\Client;
use App\Domains\Quotes\Models\Quote;
use App\Domains\Quotes\Notifications\QuoteActivity;
use App\Domains\Quotes\Notifications\QuoteMail;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);
    $this->auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create();   // create + send
    $this->finance = User::factory()->staff(SystemRole::Finance)->create();         // create, approve, send, view all
    $this->client = Client::create(['name' => 'Naledi Dube', 'company' => 'Highveld Resources', 'email' => 'naledi@example.com']);
    $s = app(QuotationSettings::class);
    $s->approval_threshold = 100000; // R100 000
    $s->save();
});

/** A quote built through the real endpoints; $price in rand excl. VAT. */
function builtQuote(User $by, int $price = 10000): Quote
{
    test()->actingAs($by)->post(route('admin.quotes.store'), ['title' => 'Due diligence', 'client_id' => Client::value('id')]);
    $quote = Quote::latest('id')->firstOrFail();
    test()->actingAs($by)->put(route('admin.quotes.update', $quote), [
        'title' => 'Due diligence', 'client_id' => Client::value('id'), 'issue_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(),
        'internal_notes' => 'Secret margin note',
        'lines' => [['kind' => 'item', 'frequency' => 'once', 'description' => 'Review', 'quantity' => 1, 'unit' => 'each', 'unit_price' => (string) $price, 'vat_applicable' => true]],
        'milestones' => [], 'payments' => [],
    ])->assertSessionHasNoErrors();

    return $quote->fresh();
}

function clientUrl(Quote $quote, string $route = 'show'): string
{
    return URL::temporarySignedRoute("quotes.public.{$route}", now()->addDays(30), ['quote' => $quote->ulid]);
}

it('sends a quote below the threshold straight to the client with the PDF and a signed link', function () {
    $quote = builtQuote($this->auctioneer, 10000);

    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote), ['message' => 'As discussed.'])->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->status)->toBe('sent')->and($quote->fingerprint)->toHaveLength(64)->and($quote->sent_at)->not->toBeNull();
    Notification::assertSentTo(new AnonymousNotifiable, QuoteMail::class, fn (QuoteMail $n, $c, $to) => $n->kind === 'sent' && $to->routes['mail'] === 'naledi@example.com'
        && str_contains($n->link, '/q/'.$quote->ulid) && str_contains($n->link, 'signature='));
});

it('requires approval above the threshold, by someone other than the preparer', function () {
    $quote = builtQuote($this->finance, 200000);

    $this->actingAs($this->finance)->post(route('admin.quotes.send', $quote))->assertSessionHasErrors('quote');
    $this->actingAs($this->finance)->post(route('admin.quotes.submit', $quote))->assertSessionHasNoErrors();
    $this->actingAs($this->finance)->post(route('admin.quotes.approve', $quote))->assertSessionHasErrors('quote');

    $other = User::factory()->staff(SystemRole::Finance)->create();
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.approve', $quote))->assertForbidden();
    $this->actingAs($other)->post(route('admin.quotes.approve', $quote))->assertSessionHasNoErrors();
    expect($quote->fresh()->status)->toBe('approved')->and($quote->fresh()->approved_by)->toBe($other->id);

    $this->actingAs($this->finance)->post(route('admin.quotes.send', $quote))->assertSessionHasNoErrors();
    expect($quote->fresh()->status)->toBe('sent');
});

it('refuses tampered and unsigned client links', function () {
    $quote = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote));
    auth()->logout();

    $this->get("/q/{$quote->ulid}")->assertForbidden();
    $this->get(clientUrl($quote).'x')->assertForbidden();
    $this->get(clientUrl($quote))->assertOk();
});

it('records the first view, notifies the preparer and never shows internal notes', function () {
    $quote = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote));
    auth()->logout();

    $page = $this->get(clientUrl($quote))->assertInertia(fn (Assert $p) => $p->component('public/quote')->where('quote.open', true));
    expect($page->getContent())->not->toContain('Secret margin note')->and($quote->fresh()->status)->toBe('viewed');
    Notification::assertSentTo($this->auctioneer, QuoteActivity::class, fn ($n) => $n->event === 'viewed');

    $this->actingAs($this->finance)->get(clientUrl($quote)); // staff views are not counted
    expect($quote->events()->where('type', 'viewed')->count())->toBe(1);
});

it('accepts with a typed name, recording time, IP and fingerprint, and emails a copy', function () {
    $quote = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote));
    auth()->logout();

    $this->post(clientUrl($quote, 'accept'), ['name' => 'Naledi Dube'])->assertSessionHasErrors('agree');
    $this->post(clientUrl($quote, 'accept'), ['name' => 'Naledi Dube', 'agree' => '1'])->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->status)->toBe('accepted')->and($quote->accepted_name)->toBe('Naledi Dube')->and($quote->accepted_ip)->toBe('127.0.0.1');
    Notification::assertSentTo(new AnonymousNotifiable, QuoteMail::class, fn (QuoteMail $n) => $n->kind === 'accepted');
    Notification::assertSentTo($this->auctioneer, QuoteActivity::class, fn ($n) => $n->event === 'accepted');
    expect(AuditEntry::where('log_name', 'quotes')->where('event', 'accepted')->exists())->toBeTrue();

    $this->post(clientUrl($quote, 'accept'), ['name' => 'Someone else', 'agree' => '1'])->assertSessionHasErrors('quote');
    expect($quote->fresh()->accepted_name)->toBe('Naledi Dube');
});

it('refuses acceptance if any figure changed after sending', function () {
    $quote = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote));
    $quote->lines()->update(['net' => 1]); // tampering directly in the database

    $this->post(clientUrl($quote, 'accept'), ['name' => 'Naledi Dube', 'agree' => '1'])->assertSessionHasErrors('quote');
    expect($quote->fresh()->status)->toBe('sent');
});

it('locks sent quotes; a new version supersedes the old one, which can no longer be accepted', function () {
    $quote = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote));

    $this->actingAs($this->auctioneer)->put(route('admin.quotes.update', $quote), ['title' => 'x'])->assertSessionHasErrors('quote');
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.version', $quote))->assertRedirect();

    $v2 = Quote::where('number', $quote->number)->where('version', 2)->firstOrFail();
    expect($v2->status)->toBe('draft')->and($v2->reference())->toBe($quote->number.'-v2')->and($v2->lines()->count())->toBe(1)
        ->and($quote->fresh()->status)->toBe('superseded');

    $this->post(clientUrl($quote, 'accept'), ['name' => 'Naledi Dube', 'agree' => '1'])->assertSessionHasErrors('quote');
});

it('lets the client decline with a reason', function () {
    $quote = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote));
    auth()->logout();

    $this->post(clientUrl($quote, 'decline'), ['reason' => 'Budget moved to next year'])->assertSessionHasNoErrors();
    expect($quote->fresh()->status)->toBe('declined')->and($quote->fresh()->decline_reason)->toBe('Budget moved to next year');
    Notification::assertSentTo($this->auctioneer, QuoteActivity::class, fn ($n) => $n->event === 'declined');
});

it('expires open quotes after their date and sends one reminder before', function () {
    $s = app(QuotationSettings::class);
    $s->reminder_days = 5;
    $s->save();

    $soon = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $soon));
    $soon->forceFill(['valid_until' => now('Africa/Johannesburg')->addDays(5)->toDateString()])->save();

    $old = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $old));
    $old->forceFill(['valid_until' => now('Africa/Johannesburg')->subDay()->toDateString()])->save();

    $this->artisan('quotes:daily')->assertSuccessful();
    $this->artisan('quotes:daily')->assertSuccessful();

    expect($old->fresh()->status)->toBe('expired')
        ->and($soon->fresh()->status)->toBe('sent')
        ->and($soon->events()->where('type', 'reminded')->count())->toBe(1);
    Notification::assertSentTimes(QuoteMail::class, 3); // 2 sends + 1 reminder

    $this->post(clientUrl($old, 'accept'), ['name' => 'Naledi Dube', 'agree' => '1'])->assertSessionHasErrors('quote');
});

it('shows buyers their quotations in their account', function () {
    $buyer = User::factory()->create(['type' => 'buyer', 'email' => 'naledi@example.com']);
    $quote = builtQuote($this->auctioneer);
    builtQuote($this->auctioneer); // draft: not shown
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote));

    $this->actingAs($buyer)->get(route('account.quotes'))->assertInertia(fn (Assert $p) => $p->component('account/quotes')->has('quotes', 1)
        ->where('quotes.0.url', fn ($u) => str_contains($u, 'signature=')));
});

it('reports the pipeline and exports CSV for staff who see all quotations', function () {
    $quote = builtQuote($this->auctioneer);
    $this->actingAs($this->auctioneer)->post(route('admin.quotes.send', $quote));

    $this->actingAs($this->auctioneer)->get(route('admin.quotes.reports'))->assertForbidden();
    $this->actingAs($this->finance)->get(route('admin.quotes.reports'))->assertInertia(fn (Assert $p) => $p
        ->where('inProgress', 1150000)->where('byStatus.sent.count', 1));

    $csv = $this->actingAs($this->finance)->get(route('admin.quotes.reports.export'))->streamedContent();
    expect($csv)->toContain('Reference')->toContain($quote->number)->toContain('11500.00');
});
