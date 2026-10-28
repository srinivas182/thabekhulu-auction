<?php

namespace App\Domains\Quotes\Notifications;

use App\Domains\Platform\Email\TemplateMail;
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

        $vars = ['name' => $q->client->name, 'reference' => $q->reference(), 'title' => $q->title, 'valid_until' => $until,
            'message' => $this->message ?: __('Please find our quotation attached.')];
        $signOff = __('Kind regards,')."\n".$company;
        $mail = match ($this->kind) {
            'sent' => TemplateMail::make('quote.sent', $vars, $this->link, $signOff),
            'reminder' => TemplateMail::make('quote.reminder', $vars, $this->link, $signOff),
            default => TemplateMail::make('quote.accepted', $vars, null, $signOff),
        };

        if (in_array($this->kind, ['sent', 'accepted'], true)) {
            $pdf = app(QuoteController::class)->renderPdf($q, app(QuotationSettings::class))->output();
            $mail->attachData($pdf, "{$q->reference()}.pdf", ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
