<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $order_id;
    public $amount;
    public $payment_method;
    public $customer_email;

    public function __construct(string $order_id, float $amount, string $payment_method, string $customer_email)
    {
        $this->order_id = $order_id;
        $this->amount = $amount;
        $this->payment_method = $payment_method;
        $this->customer_email = $customer_email;
    }

    public function build()
    {
        return $this->from('no-reply@amoacademy.rs', 'Amo Academy')
                    ->replyTo('info@amoacademy.rs')
                    ->subject('Potvrda narudžbine i instrukcije za uplatu')
                    ->view('emails.order_confirmation')
                    ->text('emails.order_confirmation_text'); // Plain text version
    }
}