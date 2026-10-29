<?php

namespace App\Domains\Payments\Notifications;

use App\Domains\Platform\Email\TemplateMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A listing's legal checks are complete: approved bidders can now pay their deposit (template "deposit.open"). */
class DepositsOpen extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $listingTitle, public readonly string $depositUrl) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return TemplateMail::make('deposit.open', ['first_name' => $notifiable->first_name ?? '', 'listing' => $this->listingTitle], $this->depositUrl);
    }
}
