<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Events\AuctionUpdated;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Notifications\AutoBidPlaced;
use App\Domains\Bidding\Notifications\ClosingSoon;
use App\Domains\Bidding\Notifications\Outbid;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Notifications\Channels\WhatsAppChannel;
use App\Domains\Notifications\Settings\WhatsAppSettings;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->listing = Listing::create([
        'category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => 'Family home', 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3),
    ]);
    $this->a = liveBidder('Alice', 'Mokoena', '1111');
    $this->b = liveBidder('Bongani', 'Zulu', '2222');
});

function liveBidder(string $first, string $last, string $number): User
{
    $u = User::factory()->create(['type' => 'buyer', 'first_name' => $first, 'last_name' => $last]);
    $ap = BidderApproval::create(['listing_id' => test()->listing->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => $number]);
    Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
    Deposit::create(['listing_id' => test()->listing->id, 'user_id' => $u->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make($number), 'status' => 'received']);

    return $u;
}

it('broadcasts the public view after each bid, without names or maximums', function () {
    Event::fake([AuctionUpdated::class]);
    app(BidEngine::class)->place($this->listing, $this->a, 1000000, 1300000);

    Event::assertDispatched(AuctionUpdated::class, function (AuctionUpdated $e) {
        $json = json_encode($e->broadcastWith());

        return $e->broadcastOn()->name === 'listing.'.$this->listing->ulid && $e->broadcastAs() === 'auction.updated'
            && $e->auction['current'] === 1000000 && $e->auction['leaderNumber'] === '1111'
            && ! str_contains($json, 'Mokoena') && ! str_contains($json, '1300000') && ! array_key_exists('mine', $e->auction);
    });
});

it('tells the previous leader they were outbid, and only them', function () {
    Notification::fake();
    $engine = app(BidEngine::class);

    $engine->place($this->listing, $this->a, 1000000, 1100000);
    $engine->place($this->listing, $this->b, 1025000);                   // A's maximum defends: nobody lost the lead
    // A is told their automatic bid rose (B's bid plus one step); B gets nothing.
    Notification::assertSentTo($this->a, AutoBidPlaced::class, fn ($n) => $n->amount === 1050000 && $n->maximum === 1100000);
    Notification::assertNotSentTo($this->a, Outbid::class);
    Notification::assertNothingSentTo($this->b);

    $engine->place($this->listing, $this->b, 1200000);                   // B takes the lead
    Notification::assertSentTo($this->a, Outbid::class, fn (Outbid $n) => $n->current === 1200000);
    Notification::assertNotSentTo($this->b, Outbid::class);
});

it('limits outbid emails and WhatsApp to one per 10 minutes, browser alerts always', function () {
    $n = new Outbid($this->listing, 1200000);

    expect($n->via($this->a))->toBe(['broadcast', 'mail', WhatsAppChannel::class])
        ->and((new Outbid($this->listing, 1300000))->via($this->a))->toBe(['broadcast']);
    $this->travel(11)->minutes();
    expect((new Outbid($this->listing, 1400000))->via($this->a))->toContain('mail');
});

it('sends WhatsApp templates only to opted-in buyers when enabled', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
    $channel = new WhatsAppChannel;
    $notification = new Outbid($this->listing, 1200000);

    $channel->send($this->a, $notification);                              // disabled
    $s = app(WhatsAppSettings::class);
    $s->enabled = true;
    $s->phone_number_id = '1234567890';
    $s->access_token = 'EAAtoken';
    $s->save();
    $channel->send($this->a, $notification);                              // not opted in
    Http::assertNothingSent();

    $this->a->forceFill(['whatsapp_number' => '+27 82 555 0000', 'whatsapp_opt_in_at' => now()])->save();
    $channel->send($this->a, $notification);

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
        && $r->hasHeader('Authorization', 'Bearer EAAtoken') && $r['to'] === '27825550000' && $r['type'] === 'template'
        && $r['template']['name'] === 'auction_outbid' && $r['template']['components'][0]['parameters'][1]['text'] === 'R 1 200 000');
});

it('sends each closing-soon alert once', function () {
    Notification::fake();
    BidEngine::state($this->listing)->update(['ends_at' => now()->addMinutes(50)]);

    $this->artisan('auctions:closing-alerts')->assertSuccessful();
    $this->artisan('auctions:closing-alerts')->assertSuccessful();
    Notification::assertSentToTimes($this->a, ClosingSoon::class, 1);

    AuctionState::query()->update(['ends_at' => now()->addMinutes(8)]);
    $this->artisan('auctions:closing-alerts');
    Notification::assertSentTo($this->a, ClosingSoon::class, fn (ClosingSoon $n) => $n->minutes === 10);
    Notification::assertSentToTimes($this->a, ClosingSoon::class, 2);
});

it('authorises private alert channels for their owner only', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
    // Channel rules were registered on the boot-time (null) broadcaster: register them on Reverb.
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');
    $payload = fn (User $u) => ['socket_id' => '1234.5678', 'channel_name' => "private-bidder.{$u->ulid}"];

    $this->actingAs($this->a)->post('/broadcasting/auth', $payload($this->a))->assertOk()->assertJsonStructure(['auth']);
    $this->actingAs($this->b)->post('/broadcasting/auth', $payload($this->a))->assertForbidden();
    expect($this->a->receivesBroadcastNotificationsOn())->toBe("bidder.{$this->a->ulid}"); // the browser subscribes with the ulid it knows
    $this->actingAs($this->a)->get('/')->assertInertia(fn ($p) => $p->where('auth.user.ulid', $this->a->ulid)->missing('auth.user.id'));
});

it('allows the WebSocket origin in the content security policy', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.options.host' => 'thabekhuludevelopment.org.za', 'broadcasting.connections.reverb.options.scheme' => 'https', 'broadcasting.connections.reverb.options.port' => 443]);

    expect($this->get('/')->headers->get('Content-Security-Policy'))->toContain('connect-src')->toContain('wss://thabekhuludevelopment.org.za');
});

it('records WhatsApp opt-in with a number and keeps the token write-only', function () {
    $this->actingAs($this->a)->put(route('account.notifications.update'), ['whatsapp' => true, 'number' => ''])->assertSessionHasErrors('number');
    $this->actingAs($this->a)->put(route('account.notifications.update'), ['whatsapp' => true, 'number' => '+27 82 555 0000'])->assertSessionHasNoErrors();
    expect($this->a->fresh()->whatsapp_opt_in_at)->not->toBeNull()->and($this->a->fresh()->whatsapp_number)->toBe('+27825550000');

    $this->actingAs($this->a)->put(route('account.notifications.update'), ['whatsapp' => false]);
    expect($this->a->fresh()->whatsapp_opt_in_at)->toBeNull();

    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $base = ['enabled' => true, 'phone_number_id' => '1234567890', 'api_version' => 'v21.0', 'language' => 'en', 'template_outbid' => 'auction_outbid', 'template_closing' => 'auction_closing_soon'];
    $this->actingAs($admin)->put(route('admin.notifications.whatsapp.update'), $base + ['access_token' => 'EAAsecret9876']);
    $this->actingAs($admin)->put(route('admin.notifications.whatsapp.update'), $base + ['access_token' => '']);
    expect(app(WhatsAppSettings::class)->access_token)->toBe('EAAsecret9876');
    $this->actingAs($admin)->get(route('admin.notifications.whatsapp'))->assertInertia(fn ($p) => $p->where('settings.token_hint', '•••• 9876')->missing('settings.access_token'));
});
