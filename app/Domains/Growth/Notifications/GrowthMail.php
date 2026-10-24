<?php

namespace App\Domains\Growth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Growth emails: saved-search digests, watchlist reminders, newsletter confirmation. */
class GrowthMail extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $lines */
    public function __construct(public readonly string $kind, public readonly string $subjectText, public readonly array $lines, public readonly ?string $actionText = null, public readonly ?string $url = null, public readonly ?string $footer = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)->subject($this->subjectText);
        foreach ($this->lines as $l) {
            $m->line($l);
        }
        if ($this->url) {
            $m->action($this->actionText ?? __('View'), $this->url);
        }

        return $this->footer ? $m->line($this->footer) : $m;
    }
}
