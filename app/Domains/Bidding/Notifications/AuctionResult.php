<?php

namespace App\Domains\Bidding\Notifications;

use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Platform\Email\TemplateMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Result email to every bidder with a deposit: won, runner-up, or not successful (with refund timing). */
class AuctionResult extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'won'|'runner_up'|'lost' $kind */
    public function __construct(public readonly Listing $listing, public readonly string $kind, public readonly ?int $amount, public readonly ?string $refundDue) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vars = ['first_name' => $notifiable->first_name ?? '', 'listing' => $this->listing->title,
            'amount' => number_format((int) $this->amount, 0, '.', ' '), 'refund_date' => (string) $this->refundDue];

        return match ($this->kind) {
            'won' => TemplateMail::make('auction.won', $vars, url(PublicListing::url($this->listing))),
            'runner_up' => TemplateMail::make('auction.runner_up', $vars),
            default => TemplateMail::make('auction.lost', $vars),
        };
    }
}
