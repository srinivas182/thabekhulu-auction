<?php

namespace App\Domains\Bidding\Notifications;

use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Notifications\Channels\WhatsAppChannel;
use App\Domains\Notifications\Settings\WhatsAppSettings;
use App\Domains\Platform\Email\TemplateMail;
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
        return TemplateMail::make('auction.outbid', ['first_name' => $notifiable->first_name ?? '', 'listing' => $this->listing->title,
            'amount' => number_format($this->current, 0, '.', ' ')], url(PublicListing::url($this->listing)));
    }

    /** @return array{template: string, params: list<string>} */
    public function toWhatsApp(object $notifiable): array
    {
        return ['template' => app(WhatsAppSettings::class)->template_outbid, 'params' => [$this->listing->title, 'R '.number_format($this->current, 0, '.', ' ')]];
    }
}
