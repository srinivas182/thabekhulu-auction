<?php

namespace App\Domains\Identity\Notifications;

use App\Domains\Platform\Email\TemplateMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The email-verification message, sent in the background like every other
 * email: a mail-server problem can no longer make registration fail.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        return TemplateMail::make('account.verify_email', [
            'first_name' => $notifiable->first_name ?? '', 'expires_minutes' => (int) config('auth.verification.expire', 60),
        ], $this->verificationUrl($notifiable));
    }
}
