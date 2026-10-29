<?php

namespace App\Domains\Settlement\Notifications;

use App\Domains\Platform\Email\TemplateMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Staff asked the winning bidder a question during settlement (editable template "settlement.question"). */
class SettlementQuestion extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $listingTitle, public readonly string $question) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return TemplateMail::make('settlement.question', ['first_name' => $notifiable->first_name ?? '', 'listing' => $this->listingTitle, 'question' => $this->question],
            route('account.purchases'));
    }
}
