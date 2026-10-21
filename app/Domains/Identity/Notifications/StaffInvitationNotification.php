<?php

namespace App\Domains\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $roleLabel,
        public readonly string $inviterName,
        public readonly int $ttlHours,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You’re invited to the Thabekhulu back office'))
            ->greeting(__('Hello,'))
            ->line(__(':inviter has invited you to join the Thabekhulu Development Group back office as :role.', [
                'inviter' => $this->inviterName,
                'role' => $this->roleLabel,
            ]))
            ->action(__('Accept invitation'), route('invitations.show', $this->token))
            ->line(__('This link expires in :hours hours and can be used once. You’ll set a password and two-factor authentication when you accept.', [
                'hours' => $this->ttlHours,
            ]))
            ->line(__('If you weren’t expecting this, you can ignore this email.'));
    }
}
