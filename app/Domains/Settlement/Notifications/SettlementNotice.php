<?php

namespace App\Domains\Settlement\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Settlement emails to buyers: deadlines, reminders, default, runner-up offer, completion. */
class SettlementNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $lines */
    public function __construct(public readonly string $kind, public readonly string $subjectText, public readonly array $lines, public readonly ?string $url = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subjectText)->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? '']));
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $this->url ? $mail->action(__('View details'), $this->url) : $mail;
    }
}
