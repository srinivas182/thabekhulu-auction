<?php

namespace App\Domains\Events\Http\Controllers;

use App\Domains\Events\Actions\EventService;
use App\Domains\Events\Models\Event;
use App\Domains\Events\Models\TicketOrder;
use App\Domains\Events\Notifications\TicketsIssued;
use App\Domains\Events\Support\TicketToken;
use App\Domains\Payments\Providers\PaystackProvider;
use App\Domains\Payments\Support\PaymentRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Public events: list, event page, ordering, and the signed tickets page. */
class EventController extends Controller
{
    /** @return array<string, mixed> */
    private static function card(Event $e): array
    {
        $tz = (string) config('platform.display_timezone');

        return [
            'slug' => $e->slug, 'title' => $e->title, 'kind' => $e->kind, 'venue' => $e->kind === 'in_person' ? $e->venue : null, 'price' => $e->price,
            'when' => $e->starts_at->setTimezone($tz)->format('l j F Y, H:i'), 'left' => max(0, $e->capacity - $e->seatsTaken()),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('public/events/index', [
            'events' => Event::where('status', 'published')->where('ends_at', '>', now())->orderBy('starts_at')->get()->map(fn (Event $e) => self::card($e)),
        ]);
    }

    public function show(string $slug, PaymentRegistry $payments): Response
    {
        $event = Event::where('slug', $slug)->whereIn('status', ['published', 'cancelled'])->firstOrFail();

        return Inertia::render('public/events/show', [
            'event' => self::card($event) + ['description' => $event->description, 'status' => $event->status, 'started' => $event->starts_at->isPast(), 'maxPerOrder' => EventService::MAX_PER_ORDER],
            'canPay' => $event->price === 0 || $payments->usable('paystack', 'small'),
        ]);
    }

    public function order(Request $request, string $slug, EventService $service, PaymentRegistry $payments): SymfonyResponse
    {
        if (filled($request->input('website'))) {
            return back(); // honeypot
        }
        $event = Event::where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email:rfc', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:20'], 'quantity' => ['required', 'integer', 'min:1', 'max:'.EventService::MAX_PER_ORDER],
            'popia' => ['accepted'],
        ], ['popia.accepted' => __('Please agree to the privacy notice to register.')]);

        if ($event->price > 0 && ! $payments->usable('paystack', 'small')) {
            throw ValidationException::withMessages(['quantity' => __('Ticket sales open soon.')]);
        }

        $order = $service->order($event, $data['name'], $data['email'], $data['mobile'] ?? null, (int) $data['quantity']);
        $ticketsUrl = TicketsIssued::link($order);

        if ($order->status === 'free') {
            return redirect($ticketsUrl);
        }

        /** @var PaystackProvider $paystack */
        $paystack = $payments->find('paystack');
        try {
            return Inertia::location($paystack->checkoutForOrder($order, $payments->credentials('paystack'), $ticketsUrl));
        } catch (\Throwable $e) {
            report($e);
            $order->forceFill(['status' => 'cancelled', 'hold_until' => null])->save();
            throw ValidationException::withMessages(['quantity' => __('Card payments are unavailable right now. Please try again shortly.')]);
        }
    }

    /** Signed link from the email or the payment page. */
    public function tickets(TicketOrder $order): Response
    {
        $order->load(['event', 'tickets']);
        $e = $order->event;
        $issued = in_array($order->status, ['paid', 'free'], true);

        return Inertia::render('public/events/tickets', [
            'order' => ['reference' => $order->reference, 'name' => $order->name, 'status' => $order->status, 'quantity' => $order->quantity, 'amount' => $order->amount],
            'event' => self::card($e) + ['onlineLink' => $issued && $e->kind === 'online' ? $e->online_link : null],
            'tickets' => $issued ? $order->tickets->map(fn ($t) => ['code' => $t->code, 'qr' => TicketToken::qr($t->ulid), 'used' => $t->checked_in_at !== null])->values() : [],
            'pdf' => $issued ? URL::signedRoute('events.tickets.pdf', $order) : null,
        ]);
    }

    public function ticketsPdf(TicketOrder $order): SymfonyResponse
    {
        abort_unless(in_array($order->status, ['paid', 'free'], true), 404);

        return response(TicketsIssued::pdf($order), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$order->reference.'.pdf"', 'Cache-Control' => 'no-store, private']);
    }
}
