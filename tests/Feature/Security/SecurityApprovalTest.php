<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Security\ApprovalGate;
use App\Domains\Security\Notifications\ApprovalCode;
use App\Domains\Settings\Settings\SecuritySettings;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->withApprovals();
    Notification::fake();
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create(['first_name' => 'Nomsa', 'last_name' => 'Khumalo', 'email' => 'nomsa@example.com']);
});

function secListing(ListingStatus $status, string $slug = 'gated-home'): Listing
{
    return Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'durban')->value('id'), 'title' => 'Gated home', 'slug' => $slug,
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => $status, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(5)]);
}

/** The code from the last approval email, plus who it went to. */
function lastCode(): array
{
    $sent = Notification::sent(new AnonymousNotifiable, ApprovalCode::class);
    $code = null;
    $to = null;
    Notification::assertSentOnDemand(ApprovalCode::class, function ($n, $channels, $notifiable) use (&$code, &$to) {
        $code = $n->code;
        $to = $notifiable->routes['mail'];

        return true;
    });

    return [$code, $to];
}

it('asks for an emailed code to delete, and deletes only with the right one', function () {
    $listing = secListing(ListingStatus::Draft);
    $this->actingAs($this->admin)->delete(route('admin.listings.destroy', $listing))->assertSessionHasErrors('approval_code');
    expect(Listing::whereKey($listing->id)->exists())->toBeTrue();

    [$code, $to] = lastCode();
    expect($to)->toBe(['srinivas@mayuraconsultancy.com', 'admin@stevemaqueens.co.za']);
    Notification::assertSentOnDemand(ApprovalCode::class, function (ApprovalCode $n) {
        $mail = implode("\n", array_merge($n->toMail(new AnonymousNotifiable)->introLines, $n->toMail(new AnonymousNotifiable)->outroLines));

        return str_contains($mail, 'Nomsa Khumalo') && str_contains($mail, 'nomsa@example.com') && str_contains($mail, 'Gated home');
    });

    $this->actingAs($this->admin)->delete(route('admin.listings.destroy', $listing), ['approval_code' => '000000'])->assertSessionHasErrors('approval_code');
    expect(Listing::whereKey($listing->id)->exists())->toBeTrue();
    $this->actingAs($this->admin)->delete(route('admin.listings.destroy', $listing), ['approval_code' => $code])->assertSessionHasNoErrors();
    expect(Listing::whereKey($listing->id)->exists())->toBeFalse();
    expect(DB::table('activity_log')->where('event', 'approval_used')->exists())->toBeTrue();
});

it('ties the code to the exact action: it can’t be reused or used for something else', function () {
    $a = secListing(ListingStatus::Draft, 'first-home');
    $b = secListing(ListingStatus::Draft, 'second-home');
    $this->actingAs($this->admin)->delete(route('admin.listings.destroy', $a));
    [$code] = lastCode();

    $this->actingAs($this->admin)->delete(route('admin.listings.destroy', $b), ['approval_code' => $code])->assertSessionHasErrors('approval_code');
    expect(Listing::whereKey($b->id)->exists())->toBeTrue();
    $this->actingAs($this->admin)->delete(route('admin.listings.destroy', $a), ['approval_code' => $code])->assertSessionHasNoErrors();
});

it('needs a code to change a public listing, but not a draft', function () {
    $live = secListing(ListingStatus::Live, 'live-home');
    $draft = secListing(ListingStatus::Draft, 'draft-home');
    $this->actingAs($this->admin)->put(route('admin.listings.update', [$live, 'details']), ['title' => 'Changed'])->assertSessionHasErrors('approval_code');
    expect($live->fresh()->title)->toBe('Gated home');

    $this->actingAs($this->admin)->put(route('admin.listings.update', [$draft, 'details']), ['title' => 'Changed draft'])->assertSessionDoesntHaveErrors('approval_code');
});

it('needs a code to change the security settings, and expires codes after 10 minutes', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update', 'security'), ['approvals_enabled' => false])->assertSessionHasErrors('approval_code');
    [$code] = lastCode();
    $this->travel(11)->minutes();
    $this->actingAs($this->admin)->put(route('admin.settings.update', 'security'), ['approvals_enabled' => false, 'approval_code' => $code])->assertSessionHasErrors('approval_code');
    expect(app(SecuritySettings::class)->refresh()->approvals_enabled)->toBeTrue();
});

it('has a logged emergency switch on the server', function () {
    $this->artisan('security:approvals off')->assertSuccessful();
    expect(ApprovalGate::enabled())->toBeFalse()
        ->and(DB::table('activity_log')->where('event', 'approvals_off')->exists())->toBeTrue();
});

it('needs a code for finance approvals, telling the approvers the amount and reference', function () {
    $this->actingAs($this->admin)->post(route('admin.confirmations.store'), ['kind' => 'deposit', 'id' => 1, 'amount' => 50000, 'reference' => 'TDG-D-2499-KAHJM', 'received_on' => today()->toDateString()])
        ->assertSessionHasErrors('approval_code');
    Notification::assertSentOnDemand(ApprovalCode::class, fn (ApprovalCode $n) => str_contains($n->summary, 'Confirm a payment received')
        && str_contains($n->summary, 'TDG-D-2499-KAHJM') && str_contains($n->summary, '50000'));
});

it('also needs a code to void a bid, deactivate staff or match a bank line to a payment', function () {
    $staff = User::factory()->staff(SystemRole::Support)->create();
    $this->actingAs($this->admin)->post(route('admin.staff.deactivate', $staff))->assertSessionHasErrors('approval_code');
    expect($staff->fresh()->deactivated_at ?? null)->toBeNull();

    foreach (['admin.bids.void', 'admin.finance.resolve'] as $name) {
        $route = app('router')->getRoutes()->getByName($name);
        $request = Request::create('/'.$route->uri(), 'POST');
        $request->setRouteResolver(fn () => $route->bind($request));
        expect(ApprovalGate::label($request))->not->toBeNull();
    }
});
