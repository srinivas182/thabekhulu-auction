<?php

namespace App\Domains\Leads\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A staff reply to a website enquiry, sent to the person who asked. */
class EnquiryReplyMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $originalSubject, public readonly string $body, public readonly string $staffName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Re: '.$this->originalSubject)->greeting(__('Hello,'));
        foreach (preg_split('/\R{2,}/', trim($this->body)) ?: [] as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail->salutation(__('Kind regards,')."\n".$this->staffName."\n".'Thabekhulu Development Group');
    }
}
