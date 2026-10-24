<?php

namespace App\Domains\Bidding\Notifications;

use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Notifications\Channels\WhatsAppChannel;
use App\Domains\Notifications\Settings\WhatsAppSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

/**
 * Sent to the previous leader when someone takes the lead. The browser alert
 * is always sent; email and WhatsApp at most once per 10 minutes per listing,
 * so a bidding war doesn't flood the bidder's phone.
 */
class Outbid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Listing $listing, public readonly int $current) {}

    /** @return list<string|class-string> */
    public function via(object $notifiable): array
    {
        $key = $notifiable instanceof Model ? (string) $notifiable->getKey() : spl_object_hash($notifiable);
        $external = Cache::add("outbid-alert:{$this->listing->id}:{$key}", true, now()->addMinutes(10));

        return $external ? ['broadcast', 'mail', WhatsAppChannel::class] : ['broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['kind' => 'outbid', 'listing' => $this->listing->ulid, 'title' => $this->listing->title, 'current' => $this->current]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('You’ve been outbid: :title', ['title' => $this->listing->title]))
            ->line(__('Another bidder has bid R :amount on :title.', ['amount' => number_format($this->current, 0, '.', ' '), 'title' => $this->listing->title]))
            ->action(__('Bid again'), url(PublicListing::url($this->listing)));
    }

    /** @return array{template: string, params: list<string>} */
    public function toWhatsApp(object $notifiable): array
    {
        return ['template' => app(WhatsAppSettings::class)->template_outbid, 'params' => [$this->listing->title, 'R '.number_format($this->current, 0, '.', ' ')]];
    }
}
