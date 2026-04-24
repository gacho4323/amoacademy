<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UplatnicaInstructions extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function build()
    {
        return $this->subject('Instrukcije za uplatu - Amo Academy')
            ->view('emails.uplatnica_instructions')
            ->with([
                'order' => $this->payment,
            ]);
    }
}