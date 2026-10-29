<?php

namespace App\Domains\Bidding\Notifications;

use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Platform\Email\TemplateMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The bidder's automatic bid raised their bid to keep them in the lead (template "auction.auto_bid"). Sent every time. */
class AutoBidPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Listing $listing, public readonly int $amount, public readonly ?int $maximum) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rand = fn (int $v): string => 'R '.number_format($v, 0, '.', ' ');

        return TemplateMail::make('auction.auto_bid', [
            'first_name' => $notifiable->first_name ?? '', 'listing' => $this->listing->title,
            'amount' => $rand($this->amount), 'maximum' => $this->maximum === null ? '' : $rand($this->maximum),
        ], PublicListing::url($this->listing));
    }
}
