<?php

namespace App\Domains\Verification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a buyer about their company/trust, proof of funds or bidding approval. */
class BidderUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $kind, public readonly string $subjectText, public readonly string $line, public readonly ?string $url = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subjectText)->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? '']))->line($this->line);

        return $this->url ? $mail->action(__('View details'), $this->url) : $mail;
    }
}
