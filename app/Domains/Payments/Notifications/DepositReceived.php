<?php

namespace App\Domains\Payments\Notifications;

use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Support\ReceiptPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirms a deposit to the buyer with the receipt attached (P7). */
class DepositReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Deposit $deposit, public readonly Receipt $receipt) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->deposit->loadMissing('listing');

        return (new MailMessage)
            ->subject(__('Deposit received: :title', ['title' => $this->deposit->listing->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? '']))
            ->line(__('We’ve received your deposit of R :amount (reference :ref). Your receipt :number is attached.', [
                'amount' => number_format($this->receipt->amount / 100, 2, '.', ' '), 'ref' => $this->deposit->reference, 'number' => $this->receipt->number,
            ]))
            ->line(__('Your refund bank account is now locked until this deposit is refunded or applied.'))
            ->action(__('View your auctions'), route('account'))
            ->attachData(ReceiptPdf::render($this->receipt), $this->receipt->number.'.pdf', ['mime' => 'application/pdf']);
    }
}
