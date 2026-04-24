<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CredentialsChangedNotification extends Notification
{
    use Queueable;

    protected $changes;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $changes)
    {
        $this->changes = $changes;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for the database.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $message = 'Your account was updated: ';
        $changesList = [];

        if (in_array('email', $this->changes)) {
            $changesList[] = 'email address';
        }
        if (in_array('password', $this->changes)) {
            $changesList[] = 'password';
        }

        $message .= implode(' and ', $changesList) . ' changed.';

        return [
            'message' => $message,
            'user_id' => $notifiable->id,
            'updated_at' => now(),
        ];
    }
}