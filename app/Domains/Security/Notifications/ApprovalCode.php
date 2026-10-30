<?php

namespace App\Domains\Security\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The approval code email. Deliberately not an editable template: it must always show who asked, what, when and from where.
 * Sent immediately (not queued), so the person waiting gets the code without delay.
 */
class ApprovalCode extends Notification
{
    public function __construct(public readonly string $code, public readonly User $requester, public readonly string $summary, public readonly string $ip) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $role = $this->requester->getRoleNames()->implode(', ') ?: 'staff';
        $lines = explode("\n", $this->summary);
        $mail = (new MailMessage)
            ->subject('Approval code: '.$lines[0])
            ->greeting('Security approval needed')
            ->line("**Requested by:** {$this->requester->name} ({$this->requester->email}, {$role})")
            ->line('**When:** '.now()->setTimezone((string) config('platform.display_timezone'))->format('j F Y, H:i').' SAST, from IP '.$this->ip);
        foreach ($lines as $i => $line) {
            $mail->line(($i === 0 ? '**Action:** ' : '').$line);
        }

        return $mail->line("**Code: {$this->code}** (valid for 10 minutes, one use, for this action only)")
            ->line('Only give this code to the person above if you expected this request. If you did not, do not share it, and check the audit trail.');
    }
}
