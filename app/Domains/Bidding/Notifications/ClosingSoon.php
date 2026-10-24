<?php

namespace App\Domains\Bidding\Notifications;

use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Notifications\Channels\WhatsAppChannel;
use App\Domains\Notifications\Settings\WhatsAppSettings;
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
        $when = $this->minutes >= 60 ? __('1 hour') : __(':m minutes', ['m' => $this->minutes]);

        return (new MailMessage)->subject(__(':title closes in :when', ['title' => $this->listing->title, 'when' => $when]))
            ->line($this->leading ? __('You’re currently the highest bidder.') : __('You’re not the highest bidder at the moment.'))
            ->line(__('A bid in the last 5 minutes extends the auction by 5 minutes.'))
            ->action(__('View the auction'), url(PublicListing::url($this->listing)));
    }

    /** @return array{template: string, params: list<string>} */
    public function toWhatsApp(object $notifiable): array
    {
        return ['template' => app(WhatsAppSettings::class)->template_closing, 'params' => [$this->listing->title, $this->minutes >= 60 ? '1 hour' : "{$this->minutes} minutes"]];
    }
}
