<?php

namespace App\Domains\Events\Notifications;

use App\Domains\Events\Models\TicketOrder;
use App\Domains\Events\Support\TicketToken;
use App\Domains\Platform\Email\TemplateMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Tickets by email: a PDF with one QR code and door code per ticket, plus a link to view them again. */
class TicketsIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TicketOrder $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function pdf(TicketOrder $order): string
    {
        $order->loadMissing(['event', 'tickets']);

        return Pdf::loadView('pdf.tickets', [
            'order' => $order, 'tz' => (string) config('platform.display_timezone'),
            'qrs' => $order->tickets->mapWithKeys(fn ($t) => [$t->id => TicketToken::qr($t->ulid)])->all(),
        ])->setPaper('a4')->output();
    }

    public static function link(TicketOrder $order): string
    {
        return URL::signedRoute('events.tickets', $order);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $e = $this->order->event;

        return TemplateMail::make('event.tickets', [
            'name' => $this->order->name, 'event' => $e->title,
            'when' => $e->starts_at->setTimezone((string) config('platform.display_timezone'))->format('l j F Y, H:i'),
            'location' => $e->kind === 'online' ? __('Join online with the link on your ticket page.') : __('Venue: :venue. Show the QR code at the door.', ['venue' => (string) $e->venue]),
        ], self::link($this->order))
            ->attachData(self::pdf($this->order), "{$this->order->reference}.pdf", ['mime' => 'application/pdf']);
    }
}
