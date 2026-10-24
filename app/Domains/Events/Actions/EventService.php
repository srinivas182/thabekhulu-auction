<?php

namespace App\Domains\Events\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Events\Models\Event;
use App\Domains\Events\Models\Ticket;
use App\Domains\Events\Models\TicketOrder;
use App\Domains\Events\Notifications\TicketsIssued;
use App\Domains\Events\Support\TicketToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Event ticketing (S9): capacity under a lock, 15-minute holds, signed single-use QR tickets. */
class EventService
{
    public const HOLD_MINUTES = 15;

    public const MAX_PER_ORDER = 4;

    public function order(Event $event, string $name, string $email, ?string $mobile, int $quantity): TicketOrder
    {
        if ($quantity < 1 || $quantity > self::MAX_PER_ORDER) {
            throw ValidationException::withMessages(['quantity' => __('Choose between 1 and :max tickets.', ['max' => self::MAX_PER_ORDER])]);
        }

        $order = DB::transaction(function () use ($event, $name, $email, $mobile, $quantity) {
            /** @var Event $locked */
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'published' || $locked->starts_at->isPast()) {
                throw ValidationException::withMessages(['quantity' => __('Tickets for this event are no longer available.')]);
            }
            $left = $locked->capacity - $locked->seatsTaken();
            if ($quantity > $left) {
                throw ValidationException::withMessages(['quantity' => $left > 0 ? __('Only :n tickets are left.', ['n' => $left]) : __('This event is full.')]);
            }
            $free = $locked->price === 0;

            return TicketOrder::create([
                'event_id' => $locked->id, 'reference' => 'TDG-E-'.strtoupper(Str::random(8)), 'name' => $name, 'email' => strtolower($email),
                'mobile' => $mobile, 'quantity' => $quantity, 'amount' => $locked->price * $quantity,
                'status' => $free ? 'free' : 'pending', 'hold_until' => $free ? null : now()->addMinutes(self::HOLD_MINUTES),
            ]);
        });

        if ($order->status === 'free') {
            $this->issue($order);
        }

        return $order;
    }

    /** A provider-confirmed payment for an order; the amount must match. */
    public function paid(TicketOrder $order, int $amountCents, string $providerRef): void
    {
        if ($order->status === 'paid') {
            return;
        }
        if ($amountCents !== $order->amount) {
            Log::warning('Ticket payment amount mismatch', ['order' => $order->reference, 'paid' => $amountCents, 'due' => $order->amount]);

            return;
        }
        if ($order->hold_until !== null && $order->hold_until->isPast()) {
            Log::notice('Ticket payment arrived after the hold expired; honoured', ['order' => $order->reference]);
        }
        $order->forceFill(['status' => 'paid', 'paid_at' => now(), 'provider_ref' => $providerRef])->save();
        $this->issue($order);
    }

    private function issue(TicketOrder $order): void
    {
        if ($order->tickets()->exists()) {
            return;
        }
        for ($i = 0; $i < $order->quantity; $i++) {
            do {
                $code = TicketToken::code();
            } while (Ticket::where('event_id', $order->event_id)->where('code', $code)->exists());
            $ticket = new Ticket(['ticket_order_id' => $order->id, 'event_id' => $order->event_id, 'code' => $code]);
            $ticket->ulid = (string) Str::ulid();
            $ticket->token_hash = TicketToken::signature($ticket->ulid);
            $ticket->save();
        }
        Audit::record('events', 'tickets_issued', "Issued {$order->quantity} ticket(s) for {$order->event->title} ({$order->reference})", $order->event);
        Notification::route('mail', $order->email)->notify(new TicketsIssued($order));
    }

    /**
     * Check in by QR payload or door code. One atomic update, so two scanners
     * can never both admit the same ticket.
     *
     * @return array{result: 'admitted'|'already'|'invalid'|'other_event', name?: string, at?: string}
     */
    public function checkIn(Event $event, string $input, User $staff): array
    {
        $ulid = TicketToken::verify($input);
        $ticket = $ulid !== null
            ? Ticket::with('order')->where('ulid', $ulid)->first()
            : Ticket::with('order')->where('event_id', $event->id)->where('code', strtoupper(trim(str_replace(' ', '', $input))))->first();

        if ($ticket === null) {
            return ['result' => 'invalid'];
        }
        if ($ticket->event_id !== $event->id) {
            return ['result' => 'other_event'];
        }

        $updated = Ticket::whereKey($ticket->id)->whereNull('checked_in_at')->update(['checked_in_at' => now(), 'checked_in_by' => $staff->id]);
        if ($updated === 0) {
            $ticket->refresh();

            return ['result' => 'already', 'name' => $ticket->order->name, 'at' => $ticket->checked_in_at?->setTimezone((string) config('platform.display_timezone'))->format('H:i') ?? ''];
        }

        return ['result' => 'admitted', 'name' => $ticket->order->name];
    }
}
