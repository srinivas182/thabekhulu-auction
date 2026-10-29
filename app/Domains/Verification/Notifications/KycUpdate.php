<?php

namespace App\Domains\Verification\Notifications;

use App\Domains\Platform\Email\TemplateMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a buyer the outcome of their verification or bank account check. */
class KycUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'verified'|'rejected'|'info'|'bank_verified'|'bank_rejected'|'bank_info' $kind */
    public function __construct(public readonly string $kind, public readonly ?string $reason = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vars = ['first_name' => $notifiable->first_name ?? '', 'reason' => (string) $this->reason];

        return match ($this->kind) {
            'verified' => TemplateMail::make('kyc.verified', $vars, route('account.bank')),
            'rejected' => TemplateMail::make('kyc.rejected', $vars, route('account.verification')),
            'info' => TemplateMail::make('kyc.more_info', $vars, route('account.verification')),
            'bank_verified' => TemplateMail::make('bank.verified', $vars),
            'bank_info' => TemplateMail::make('bank.more_info', $vars, route('account.bank')),
            default => TemplateMail::make('bank.rejected', $vars, route('account.bank')),
        };
    }
}
