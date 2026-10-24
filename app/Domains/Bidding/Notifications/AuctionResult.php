<?php

namespace App\Domains\Bidding\Notifications;

use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
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
        $mail = (new MailMessage)->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? '']));
        $title = $this->listing->title;

        return match ($this->kind) {
            'won' => $mail->subject(__('You’re the winning bidder: :title', ['title' => $title]))
                ->line(__('Your bid of R :amount is the winning bid, subject to the Rules of Auction and the conditions of sale.', ['amount' => number_format((int) $this->amount, 0, '.', ' ')]))
                ->line(__('Our team will contact you within one working day about the next steps and signing.'))
                ->action(__('View the listing'), url(PublicListing::url($this->listing))),
            'runner_up' => $mail->subject(__('Auction result: :title', ['title' => $title]))
                ->line(__('You were the second-highest bidder. Your deposit stays held until :date in case the sale to the winning bidder does not complete; we will then refund it in full.', ['date' => $this->refundDue]))
                ->line(__('We will contact you if the property becomes available to you.')),
            default => $mail->subject(__('Auction result: :title', ['title' => $title]))
                ->line(__('Thank you for bidding. Your bid was not successful this time.'))
                ->line(__('Your deposit is scheduled for refund on :date to your verified bank account (or through the escrow provider that holds it). We will email your refund receipt.', ['date' => $this->refundDue])),
        };
    }
}
