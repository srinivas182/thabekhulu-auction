<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Events\Actions\EventService;
use App\Domains\Events\Models\Event;
use App\Domains\Events\Models\Ticket;
use App\Domains\Events\Models\TicketOrder;
use App\Domains\Events\Notifications\TicketsIssued;
use App\Domains\Events\Support\TicketToken;
use App\Domains\Payments\Models\PaymentProviderSetting;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);
});

function makeEvent(int $priceCents = 0, int $capacity = 50, string $status = 'published'): Event
{
    static $n = 0;
    $n++;

    return Event::create(['title' => "Investor briefing {$n}", 'slug' => "investor-briefing-{$n}", 'kind' => 'in_person', 'venue' => 'Sandton Convention Centre',
        'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(10)->addHours(2), 'price' => $priceCents, 'capacity' => $capacity, 'status' => $status]);
}

function register(Event $e, int $qty = 1, array $extra = [])
{
    return test()->post(route('events.order', $e->slug), ['name' => 'Naledi Dube', 'email' => 'naledi@example.com', 'quantity' => $qty, 'popia' => '1'] + $extra);
}

it('signs ticket QR codes so they can’t be forged', function () {
    $ulid = (string) Str::ulid();
    $payload = TicketToken::payload($ulid);

    expect(TicketToken::verify($payload))->toBe($ulid)
        ->and(TicketToken::verify(substr($payload, 0, -1).(str_ends_with($payload, 'a') ? 'b' : 'a')))->toBeNull()
        ->and(TicketToken::verify('TDGT1.'.$ulid.'.'.str_repeat('0', 32)))->toBeNull()
        ->and(TicketToken::verify('nonsense'))->toBeNull()
        ->and(TicketToken::code())->toMatch('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/');
});

it('registers for a free event, emails the tickets and shows them on a signed page only', function () {
    $e = makeEvent();
    $this->get('/events')->assertOk()->assertInertia(fn (Assert $p) => $p->component('public/events/index')->has('events', 1));

    register($e, 2)->assertRedirect();
    $order = TicketOrder::firstOrFail();
    expect($order->status)->toBe('free')->and(Ticket::count())->toBe(2);
    Notification::assertSentOnDemand(TicketsIssued::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'naledi@example.com');

    $this->get(TicketsIssued::link($order))->assertOk()->assertInertia(fn (Assert $p) => $p->has('tickets', 2)->where('tickets.0.qr', fn ($qr) => str_starts_with($qr, 'data:image/png;base64,')));
    $this->get(route('events.tickets', $order))->assertForbidden(); // unsigned
});

it('requires consent and ignores the honeypot', function () {
    $e = makeEvent();
    $this->post(route('events.order', $e->slug), ['name' => 'x', 'email' => 'x@example.com', 'quantity' => 1])->assertSessionHasErrors('popia');
    register($e, 1, ['website' => 'spam'])->assertRedirect();
    expect(TicketOrder::count())->toBe(0);
});

it('enforces capacity, counting pending payments only while they are held', function () {
    $free = makeEvent(0, 3);
    register($free, 2);
    register($free, 2)->assertSessionHasErrors('quantity');
    expect(TicketOrder::count())->toBe(1);

    $paid = makeEvent(15000, 2);
    app(EventService::class)->order($paid, 'A', 'a@example.com', null, 2); // pending, held 15 minutes
    expect(fn () => app(EventService::class)->order($paid, 'B', 'b@example.com', null, 1))->toThrow(ValidationException::class);

    $this->travel(16)->minutes();
    expect(app(EventService::class)->order($paid, 'B', 'b@example.com', null, 1)->status)->toBe('pending');

    $cancelled = makeEvent(0, 10, 'cancelled');
    expect(fn () => app(EventService::class)->order($cancelled, 'C', 'c@example.com', null, 1))->toThrow(ValidationException::class);
});

it('takes card payment through Paystack and issues tickets only for the right amount', function () {
    $e = makeEvent(15000, 10);
    register($e, 1)->assertSessionHasErrors('quantity'); // card payments not set up yet

    PaymentProviderSetting::create(['key' => 'paystack', 'enabled' => true, 'mode' => 'sandbox', 'credentials' => ['secret_key' => 'sk_test_abc']]);
    // Http::fake keeps its first match, so the verify answer reads these variables.
    $verify = ['amount' => 0, 'reference' => ''];
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.com/xyz']]),
        'api.paystack.co/transaction/verify/*' => function () use (&$verify) {
            return Http::response(['data' => ['status' => 'success', 'currency' => 'ZAR', 'amount' => $verify['amount'], 'metadata' => ['order_reference' => $verify['reference']]]]);
        },
    ]);
    $this->post(route('events.order', $e->slug), ['name' => 'Naledi', 'email' => 'n@example.com', 'quantity' => 2, 'popia' => '1'], ['X-Inertia' => 'true'])
        ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/xyz');
    $order = TicketOrder::firstOrFail();
    expect($order->status)->toBe('pending')->and($order->amount)->toBe(30000);

    $send = function (int $amount) use ($order, &$verify) {
        $verify = ['amount' => $amount, 'reference' => $order->reference];
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $order->provider_ref.'-'.$amount]]);

        return $this->call('POST', route('webhooks.payments', 'paystack'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_abc')], $body);
    };

    $send(100)->assertOk();             // wrong amount
    expect($order->fresh()->status)->toBe('pending')->and(Ticket::count())->toBe(0);
    $send(30000)->assertOk();
    expect($order->fresh()->status)->toBe('paid')->and(Ticket::count())->toBe(2);
});

it('admits each ticket once, by QR or door code, for its own event only', function () {
    $e = makeEvent();
    $other = makeEvent();
    $staff = User::factory()->staff(SystemRole::Support)->create();
    $staff->givePermissionTo('events.checkin');
    $order = app(EventService::class)->order($e, 'Naledi Dube', 'n@example.com', null, 2);
    [$t1, $t2] = $order->tickets()->orderBy('id')->get()->all();
    $scan = fn (Event $ev, string $code) => $this->actingAs($staff)->postJson(route('admin.events.checkin.store', $ev), ['code' => $code]);

    $scan($e, TicketToken::payload($t1->ulid))->assertOk()->assertJson(['result' => 'admitted', 'name' => 'Naledi Dube', 'checkedIn' => 1]);
    $scan($e, TicketToken::payload($t1->ulid))->assertJson(['result' => 'already']);
    $scan($e, 'TDGT1.'.$t2->ulid.'.'.str_repeat('a', 32))->assertJson(['result' => 'invalid']);  // forged
    $scan($other, TicketToken::payload($t2->ulid))->assertJson(['result' => 'other_event']);
    $scan($e, strtolower($t2->code))->assertJson(['result' => 'admitted', 'checkedIn' => 2]); // door code, any case

    $t3 = app(EventService::class)->order($e, 'Sipho', 's@example.com', null, 1)->tickets()->firstOrFail();
    $scan($e, strtoupper(TicketToken::payload($t3->ulid)))->assertJson(['result' => 'admitted']); // QR text upper-cased by a scanner gun

    $this->actingAs(User::factory()->staff(SystemRole::Finance)->create())->postJson(route('admin.events.checkin.store', $e), ['code' => $t2->code])->assertForbidden();
});

it('lets event managers create and publish events', function () {
    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $this->actingAs($admin)->post(route('admin.events.store'), ['title' => 'Mining investor day', 'kind' => 'online', 'online_link' => 'https://meet.example.com/abc',
        'starts_at' => now()->addDays(5)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(5)->addHours(2)->format('Y-m-d\TH:i'), 'price' => 0, 'capacity' => 200])->assertRedirect();
    $e = Event::firstOrFail();
    expect($e->status)->toBe('draft')->and($e->slug)->toBe('mining-investor-day');

    $this->get('/events/mining-investor-day')->assertNotFound(); // drafts aren't public
    $this->actingAs($admin)->put(route('admin.events.status', $e), ['status' => 'published']);
    $this->get('/events/mining-investor-day')->assertOk()->assertInertia(fn (Assert $p) => $p->component('public/events/show')->missing('event.online_link'));
});
