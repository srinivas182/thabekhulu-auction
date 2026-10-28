<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Bidding\Notifications\Outbid;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Platform\Email\EmailTemplates;
use App\Domains\Platform\Email\TemplateMail;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function templListing(string $title = 'Family home'): Listing
{
    return Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'sandton')->value('id'), 'title' => $title, 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
}

it('renders every default email completely, with its required details', function () {
    foreach (EmailTemplates::all() as $key => $t) {
        $mail = TemplateMail::make($key, $t['example'], $t['button'] !== null ? 'https://example.test/go' : null);
        $html = (string) $mail->render();
        expect($mail->subject)->not->toBe('')->not->toContain('{{')
            ->and($html)->not->toContain('{{')->toContain('Kind regards');
        $all = $t['subject'].$t['greeting'].$t['body'].$t['after'];
        foreach ($t['required'] as $r) {
            expect($all)->toContain('{{'.$r.'}}');
        }
        foreach (TemplateMail::placeholders($all) as $p) {
            expect(array_keys($t['variables']))->toContain($p);
        }
    }
});

it('uses an admin’s wording in the real email, with the system’s link unchanged', function () {
    $this->actingAs($this->admin)->put(route('admin.email-templates.update', 'auction.outbid'), [
        'subject' => 'Heads up: {{listing}} has a higher bid', 'greeting' => 'Hi {{first_name}},',
        'body' => 'The bid is now R {{amount}}.', 'after' => '', 'button' => 'Place a higher bid',
    ])->assertSessionHasNoErrors();

    $l = templListing();
    $buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Thandi']);
    $mail = (new Outbid($l, 3300000))->toMail($buyer);

    expect($mail->subject)->toBe('Heads up: Family home has a higher bid')
        ->and($mail->greeting)->toBe('Hi Thandi,')
        ->and($mail->actionText)->toBe('Place a higher bid')
        ->and($mail->actionUrl)->toBe(url('/auctions/homes/sandton/family-home'))
        ->and(implode(' ', $mail->introLines))->toContain('R 3 300 000');
});

it('refuses wording that drops a required detail or uses an unknown one', function () {
    $base = ['subject' => 'Outbid on {{listing}}', 'greeting' => 'Hello {{first_name}},', 'after' => '', 'button' => 'Bid again'];
    $this->actingAs($this->admin)->put(route('admin.email-templates.update', 'auction.outbid'), $base + ['body' => 'Someone bid more.'])->assertSessionHasErrors('body');
    $this->actingAs($this->admin)->put(route('admin.email-templates.update', 'auction.outbid'), $base + ['body' => 'The bid is R {{amont}}. {{amount}}'])->assertSessionHasErrors('body');
    expect(DB::table('email_templates')->count())->toBe(0);
});

it('resets to the default and previews unsaved wording', function () {
    $this->actingAs($this->admin)->put(route('admin.email-templates.update', 'bid.code'), ['subject' => 'Code {{code}}', 'greeting' => '', 'body' => 'Use {{code}} for R {{amount}}.', 'after' => ''])->assertSessionHasNoErrors();
    expect(TemplateMail::current('bid.code')['edited'])->toBeTrue();
    $this->actingAs($this->admin)->delete(route('admin.email-templates.reset', 'bid.code'))->assertRedirect();
    expect(TemplateMail::current('bid.code')['subject'])->toBe(EmailTemplates::all()['bid.code']['subject']);

    $r = $this->actingAs($this->admin)->postJson(route('admin.email-templates.preview', 'bid.code'), ['subject' => 'Preview {{code}}', 'greeting' => 'Hi', 'body' => 'Unsaved {{code}} {{amount}}', 'after' => ''])->assertOk();
    expect($r->json('subject'))->toBe('Preview 482913')->and($r->json('html'))->toContain('Unsaved 482913');
});

it('keeps details as plain text, never formatting', function () {
    $l = templListing('Home [click here](https://evil.example)');
    $html = (string) (new Outbid($l, 1000000))->toMail(User::factory()->create(['type' => 'buyer']))->render();
    expect($html)->not->toContain('href="https://evil.example"');
});

it('keeps the editor to settings managers and sends tests only when email is on', function () {
    $support = User::factory()->staff(SystemRole::Support)->create();
    $this->actingAs($support)->get(route('admin.email-templates.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.email-templates.index'))->assertOk()->assertInertia(fn ($p) => $p->has('templates', count(EmailTemplates::all())));

    Notification::fake();
    $this->actingAs($this->admin)->post(route('admin.email-templates.test', 'auction.won'))->assertSessionHasNoErrors();
    Notification::assertCount(1);

    $this->withEmailProvider(false);
    $this->actingAs($this->admin)->post(route('admin.email-templates.test', 'auction.won'))->assertSessionHasErrors('test');
});
