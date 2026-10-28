<?php

namespace App\Domains\Payments\Notifications;

use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Support\ReceiptPdf;
use App\Domains\Platform\Email\TemplateMail;
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

        return TemplateMail::make('deposit.received', [
            'first_name' => $notifiable->first_name ?? '', 'listing' => $this->deposit->listing->title,
            'amount' => number_format($this->receipt->amount / 100, 2, '.', ' '), 'reference' => $this->deposit->reference, 'receipt_number' => $this->receipt->number,
        ], route('account'))
            ->attachData(ReceiptPdf::render($this->receipt), $this->receipt->number.'.pdf', ['mime' => 'application/pdf']);
    }
}
