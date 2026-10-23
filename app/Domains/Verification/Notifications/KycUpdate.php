<?php

namespace App\Domains\Verification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a buyer the outcome of their verification or bank account check. */
class KycUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'verified'|'rejected'|'info'|'bank_verified'|'bank_rejected' $kind */
    public function __construct(public readonly string $kind, public readonly ?string $reason = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? '']));

        return match ($this->kind) {
            'verified' => $mail->subject(__('You’re verified to bid'))->line(__('Your identity has been verified. Next, add the bank account we refund deposits to.'))->action(__('Add your bank account'), route('account.bank')),
            'rejected' => $mail->subject(__('We couldn’t verify your identity'))->line(__('Reason: :reason', ['reason' => $this->reason]))->line(__('You can correct your details and submit again.'))->action(__('Update your details'), route('account.verification')),
            'info' => $mail->subject(__('We need a little more information'))->line((string) $this->reason)->action(__('Update your details'), route('account.verification')),
            'bank_verified' => $mail->subject(__('Your refund account is verified'))->line(__('Deposit refunds will be paid to this account.')),
            default => $mail->subject(__('We couldn’t verify your bank account'))->line(__('Reason: :reason', ['reason' => $this->reason]))->action(__('Update your bank account'), route('account.bank')),
        };
    }
}
