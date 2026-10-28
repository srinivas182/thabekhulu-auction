<?php

namespace App\Domains\Bidding\Notifications;

use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Notifications\Channels\WhatsAppChannel;
use App\Domains\Notifications\Settings\WhatsAppSettings;
use App\Domains\Platform\Email\TemplateMail;
use App\Domains\Settings\Settings\AuctionSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Closes in 1 hour / 10 minutes" to approved, paid bidders on that listing. */
class ClosingSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Listing $listing, public readonly int $minutes, public readonly bool $leading) {}

    /** @return list<string|class-string> */
    public function via(object $notifiable): array
    {
        return ['mail', WhatsAppChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $auction = app(AuctionSettings::class);

        return TemplateMail::make('auction.closing_soon', [
            'first_name' => $notifiable->first_name ?? '', 'listing' => $this->listing->title,
            'time_left' => $this->minutes >= 60 ? __('1 hour') : __(':m minutes', ['m' => $this->minutes]),
            'position' => $this->leading ? __('You’re currently the highest bidder.') : __('You’re not the highest bidder at the moment.'),
            'window_minutes' => $auction->soft_close_window_minutes, 'extension_minutes' => $auction->soft_close_extension_minutes,
        ], url(PublicListing::url($this->listing)));
    }

    /** @return array{template: string, params: list<string>} */
    public function toWhatsApp(object $notifiable): array
    {
        return ['template' => app(WhatsAppSettings::class)->template_closing, 'params' => [$this->listing->title, $this->minutes >= 60 ? '1 hour' : "{$this->minutes} minutes"]];
    }
}
