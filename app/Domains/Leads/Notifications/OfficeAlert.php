<?php

namespace App\Domains\Leads\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the Thabekhulu office about a new lead or enquiry. */
class OfficeAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $subjectLine, public readonly string $summary, public readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->subjectLine)->line($this->summary)->action(__('Open in the back office'), $this->url);
    }
}
