<?php

namespace App\Domains\Quotes\Notifications;

use App\Domains\Quotes\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the staff member who prepared a quotation that the client viewed, accepted or declined it. */
class QuoteActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Quote $quote, public readonly string $event) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $q = $this->quote;
        $client = $q->client->company ?: $q->client->name;
        $line = match ($this->event) {
            'viewed' => "{$client} opened quotation {$q->reference()}.",
            'accepted' => "{$client} accepted quotation {$q->reference()} ({$q->accepted_name}).",
            default => "{$client} declined quotation {$q->reference()}.".($q->getAttribute('decline_reason') ? ' Reason: '.$q->getAttribute('decline_reason') : ''),
        };

        return (new MailMessage)->subject(ucfirst($this->event).": {$q->reference()}")->line($line)
            ->action(__('Open the quotation'), route('admin.quotes.edit', $q));
    }
}
