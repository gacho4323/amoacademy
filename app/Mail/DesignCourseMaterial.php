<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DesignCourseMaterial extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function build()
    {
        return $this->to($this->user->email)
        ->subject('Materijal za Design Kurs')
        ->view('emails.design-course')
        ->with([
            'user' => $this->user,
        ]);
    }
}
