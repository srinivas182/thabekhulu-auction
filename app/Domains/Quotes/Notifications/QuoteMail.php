<?php

namespace App\Domains\Quotes\Notifications;

use App\Domains\Quotes\Http\Controllers\QuoteController;
use App\Domains\Quotes\Models\Quote;
use App\Domains\Quotes\Settings\QuotationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails to clients about a quotation: the quotation itself, a reminder
 * before it expires, and the confirmation copy after acceptance. The PDF
 * is attached to "sent" and "accepted" emails.
 */
class QuoteMail extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'sent'|'reminder'|'accepted' $kind */
    public function __construct(public readonly Quote $quote, public readonly string $kind, public readonly string $link, public readonly ?string $message = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $q = $this->quote;
        $company = app(QuotationSettings::class)->company_name;
        $until = $q->valid_until->format('j F Y');

        $mail = match ($this->kind) {
            'sent' => (new MailMessage)->subject("Quotation {$q->reference()}: {$q->title}")
                ->greeting(__('Hello :name,', ['name' => $q->client->name]))
                ->line($this->message ?: __('Please find our quotation attached.'))
                ->line(__('You can view, download and accept it online. It is valid until :date.', ['date' => $until]))
                ->action(__('View quotation'), $this->link),
            'reminder' => (new MailMessage)->subject("Reminder: quotation {$q->reference()} expires on {$until}")
                ->greeting(__('Hello :name,', ['name' => $q->client->name]))
                ->line(__('This is a reminder that our quotation “:title” is valid until :date.', ['title' => $q->title, 'date' => $until]))
                ->action(__('View quotation'), $this->link),
            default => (new MailMessage)->subject("Accepted: quotation {$q->reference()}")
                ->greeting(__('Hello :name,', ['name' => $q->client->name]))
                ->line(__('Thank you for accepting our quotation “:title”. A copy with your acceptance record is attached.', ['title' => $q->title]))
                ->line(__('We will be in touch about the next steps.')),
        };

        if (in_array($this->kind, ['sent', 'accepted'], true)) {
            $pdf = app(QuoteController::class)->renderPdf($q, app(QuotationSettings::class))->output();
            $mail->attachData($pdf, "{$q->reference()}.pdf", ['mime' => 'application/pdf']);
        }

        return $mail->salutation(__('Kind regards,')."\n".$company);
    }
}
