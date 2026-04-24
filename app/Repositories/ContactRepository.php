<?php

namespace App\Repositories;

use App\Interfaces\ContactInterface;
use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;

class ContactRepository implements ContactInterface
{
    public function sendContactMessage(array $data): void
    {   //gacanovicivan@gmail.com
        try {
            Mail::to('support@amoacademy.net')->send(new ContactMessage($data));
        } catch (\Exception $e) {
            throw new \Exception('Failed to send contact message: ' . $e->getMessage());
        }
    }
}