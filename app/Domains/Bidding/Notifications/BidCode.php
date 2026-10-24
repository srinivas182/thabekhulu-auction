<?php

namespace App\Domains\Bidding\Notifications;

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
        return (new MailMessage)->subject(__('Your bid confirmation code: :code', ['code' => $this->code]))
            ->line(__('Use this code to confirm your bid of R :amount on :title. It expires in 10 minutes.', ['amount' => number_format($this->amount, 0, '.', ' '), 'title' => $this->listingTitle]))
            ->line(__('If you didn’t try to bid, change your password and contact us.'));
    }
}
