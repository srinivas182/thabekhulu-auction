<?php

namespace App\Domains\Bidding\Notifications;

use App\Domains\Platform\Email\TemplateMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One-time code for a large bid (B6). Sent immediately, not queued. */
class BidCode extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code, public readonly string $listingTitle, public readonly int $amount) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return TemplateMail::make('bid.code', ['first_name' => $notifiable->first_name ?? '', 'code' => $this->code,
            'amount' => number_format($this->amount, 0, '.', ' '), 'listing' => $this->listingTitle]);
    }
}
