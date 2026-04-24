<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseExtendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $course;
    protected $newExpiryDate;
    protected $cost;

    public function __construct($course, $newExpiryDate, $cost)
    {
        $this->course = $course;
        $this->newExpiryDate = $newExpiryDate;
        $this->cost = $cost;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Course Extension Confirmation')
            ->markdown('emails.course_extended', [
                'course' => $this->course,
                'newExpiryDate' => $this->newExpiryDate,
                'cost' => $this->cost,
                'notifiable' => $notifiable,
            ]);
    }
}