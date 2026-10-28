<?php

namespace App\Domains\Identity\Notifications;

use App\Domains\Platform\Email\TemplateMail;
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
        return TemplateMail::make('staff.invitation', ['inviter' => $this->inviterName, 'role' => $this->roleLabel, 'expires_hours' => $this->ttlHours],
            route('invitations.show', $this->token));
    }
}
