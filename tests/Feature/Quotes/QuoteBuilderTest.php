<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Quotes\Models\Client;
use App\Domains\Quotes\Models\Quote;
use App\Domains\Quotes\Models\QuoteTemplate;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Domains\Quotes\Support\QuoteNumber;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\QuoteSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([AccessSeeder::class, QuoteSeeder::class]);
    $this->finance = User::factory()->staff(SystemRole::Finance)->create();
    $this->auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create();
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $this->client = Client::create(['name' => 'Naledi Dube', 'company' => 'Highveld Resources (Pty) Ltd', 'email' => 'naledi@example.com']);
    $settings = app(QuotationSettings::class);
    $settings->bank_details = "[Bank]\nAccount: [number]";
    $settings->save();
});

function newQuote(User $by, ?string $template = null): Quote
{
    test()->actingAs($by)->post(route('admin.quotes.store'), [
        'title' => 'Due diligence: coal right', 'client_id' => Client::value('id'),
        'template_id' => $template ? QuoteTemplate::where('name', $template)->value('id') : null,
    ])->assertSessionHasNoErrors();

    return Quote::latest('id')->firstOrFail();
}

function payload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Due diligence: coal right', 'client_id' => Client::value('id'), 'issue_date' => '2026-10-23', 'valid_until' => '2026-11-22',
        'payment_method' => 'EFT', 'scope' => '<p>Review<script>x</script></p>', 'internal_notes' => 'Margin 35% — do not share',
        'lines' => [
            ['kind' => 'heading', 'frequency' => 'once', 'description' => 'Review'],
            ['kind' => 'item', 'frequency' => 'once', 'description' => 'Title and right review', 'quantity' => 1.5, 'unit' => 'day', 'unit_price' => '1000', 'discount_type' => 'percent', 'discount_value' => 10, 'vat_applicable' => true],
            ['kind' => 'item', 'frequency' => 'monthly', 'description' => 'Data room', 'quantity' => 1, 'unit' => 'month', 'unit_price' => '500', 'vat_applicable' => true],
        ],
        'milestones' => [['name' => 'Document collection', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-07']],
        'payments' => [['label' => 'Deposit', 'percent' => 50, 'due' => 'On acceptance'], ['label' => 'Balance', 'percent' => 50, 'due' => 'On report']],
        'once_total' => 1, // ignored: the server recalculates
    ], $overrides);
}

it('numbers quotations sequentially per year without gaps', function () {
    $year = now('Africa/Johannesburg')->format('Y');

    expect(QuoteNumber::next())->toBe("TDG-Q-{$year}-0001")
        ->and(QuoteNumber::next())->toBe("TDG-Q-{$year}-0002");

    $quote = newQuote($this->finance);
    expect($quote->number)->toBe("TDG-Q-{$year}-0003")->and($quote->reference())->toBe("TDG-Q-{$year}-0003");
});

it('starts from a template with defaults from settings', function () {
    $quote = newQuote($this->finance, 'Mining due diligence');

    expect($quote->lines()->count())->toBe(4)
        ->and($quote->milestones()->count())->toBe(3)
        ->and((int) $quote->payments()->sum('percent_bps'))->toBe(10000)
        ->and($quote->vat_rate_bps)->toBe(1500)
        ->and($quote->valid_until->diffInDays($quote->issue_date, true))->toEqual(30.0)
        ->and($quote->status)->toBe('draft');
});

it('recalculates totals on the server, keeping ongoing costs separate', function () {
    $quote = newQuote($this->finance);

    $this->actingAs($this->finance)->put(route('admin.quotes.update', $quote), payload())->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->once_subtotal)->toBe(150000)
        ->and($quote->once_discount)->toBe(15000)
        ->and($quote->once_vat)->toBe(20250)
        ->and($quote->once_total)->toBe(155250)
        ->and($quote->monthly_total)->toBe(57500)
        ->and($quote->lines()->count())->toBe(3)
        ->and($quote->scope)->not->toContain('script');
});

it('requires payment percentages to total 100% and valid line data', function () {
    $quote = newQuote($this->finance);

    $this->actingAs($this->finance)->put(route('admin.quotes.update', $quote), payload(['payments' => [['label' => 'Deposit', 'percent' => 60]]]))
        ->assertSessionHasErrors('payments');
    $this->actingAs($this->finance)->put(route('admin.quotes.update', $quote), payload(['lines' => [['kind' => 'item', 'frequency' => 'weekly', 'description' => '']]]))
        ->assertSessionHasErrors(['lines.0.frequency', 'lines.0.description']);
});

it('produces a PDF without internal notes', function () {
    $quote = newQuote($this->finance);
    $this->actingAs($this->finance)->put(route('admin.quotes.update', $quote), payload());

    $this->actingAs($this->finance)->get(route('admin.quotes.pdf', $quote))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $html = view('pdf.quote', ['quote' => $quote->fresh()->load(['lines', 'milestones', 'payments', 'client', 'listing']), 'company' => app(QuotationSettings::class)])->render();
    expect($html)->toContain($quote->number)
        ->toContain('R 1 552.50')
        ->toContain('R 575.00')
        ->toContain('Highveld Resources (Pty) Ltd')
        ->not->toContain('Margin 35%');
});

it('shows staff only their own quotations unless they may see all', function () {
    $mine = newQuote($this->auctioneer);
    $theirs = newQuote($this->finance);

    $this->actingAs($this->auctioneer)->get(route('admin.quotes.index'))->assertInertia(fn (Assert $p) => $p->has('quotes.data', 1));
    $this->actingAs($this->auctioneer)->get(route('admin.quotes.edit', $theirs))->assertForbidden();
    $this->actingAs($this->auctioneer)->get(route('admin.quotes.pdf', $theirs))->assertForbidden();
    $this->actingAs($this->auctioneer)->get(route('admin.quotes.edit', $mine))->assertOk();
    $this->actingAs($this->finance)->get(route('admin.quotes.index'))->assertInertia(fn (Assert $p) => $p->has('quotes.data', 2));
});

it('limits catalogue, templates and settings to quotes.settings', function () {
    $this->actingAs($this->finance)->get(route('admin.quotes.catalogue'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.quotes.catalogue'))->assertOk();

    $this->actingAs($this->admin)->put(route('admin.quotes.settings.update'), [
        'company_name' => 'Thabekhulu Development Group (Pty) Ltd', 'vat_rate' => 15, 'validity_days' => 14,
        'approval_threshold' => 250000, 'reminder_days' => 3, 'number_prefix' => 'TDG-Q',
    ])->assertSessionHasNoErrors();
    expect(app(QuotationSettings::class)->vat_rate_bps)->toBe(1500)->and(app(QuotationSettings::class)->validity_days)->toBe(14);

    $this->actingAs($this->admin)->put(route('admin.quotes.settings.update'), [
        'company_name' => 'x', 'vat_rate' => 15, 'validity_days' => 14, 'approval_threshold' => 0, 'reminder_days' => 3, 'number_prefix' => 'bad prefix',
    ])->assertSessionHasErrors('number_prefix');

    $support = User::factory()->staff(SystemRole::Support)->create();
    $this->actingAs($support)->get(route('admin.quotes.index'))->assertForbidden();
});

it('links clients to buyer accounts and lets only first drafts be deleted', function () {
    $buyer = User::factory()->create(['email' => 'buyer@example.com', 'type' => 'buyer']);
    $this->actingAs($this->finance)->post(route('admin.quotes.clients.store'), ['name' => 'Buyer', 'email' => 'Buyer@Example.com']);
    expect(Client::where('email', 'buyer@example.com')->value('user_id'))->toBe($buyer->id);

    $quote = newQuote($this->finance);
    $quote->forceFill(['status' => 'sent'])->save();
    $this->actingAs($this->finance)->delete(route('admin.quotes.destroy', $quote))->assertForbidden();
    $this->actingAs($this->finance)->put(route('admin.quotes.update', $quote), payload())->assertSessionHasErrors('quote');
});

it('saves a quotation as a reusable template', function () {
    $quote = newQuote($this->admin);
    $this->actingAs($this->admin)->put(route('admin.quotes.update', $quote), payload());

    $this->actingAs($this->admin)->post(route('admin.quotes.template', $quote), ['name' => 'Coal due diligence'])->assertSessionHasNoErrors();
    $template = QuoteTemplate::where('name', 'Coal due diligence')->firstOrFail();

    expect($template->needs_review)->toBeFalse()->and(count($template->content['lines']))->toBe(3);
    expect(newQuote($this->admin, 'Coal due diligence')->lines()->count())->toBe(3);
});
