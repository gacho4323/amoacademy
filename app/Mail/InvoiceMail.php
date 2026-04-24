<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;
    public $invoicePath;
    public $fiscalPath;

    public function __construct(Payment $payment, string $invoicePath, string $fiscalPath = null)
    {
        $this->payment = $payment;
        $this->invoicePath = $invoicePath;
        $this->fiscalPath = $fiscalPath;
    }

    public function build()
    {
        $mail = $this->subject('Vaša faktura i fiskalni račun: #' . $this->payment->payment_id)
            ->view('emails.invoice');

        Log::info('Attempting to attach invoice PDFs.', [
            'invoice_path' => $this->invoicePath,
            'invoice_exists' => Storage::disk('public')->exists($this->invoicePath),
            'fiscal_path' => $this->fiscalPath,
            'fiscal_exists' => ($this->fiscalPath && Storage::disk('public')->exists($this->fiscalPath)),
        ]);

        // Attach invoice PDF
        if (Storage::disk('public')->exists($this->invoicePath)) {
            $mail->attach(Storage::disk('public')->path($this->invoicePath), [
                'as' => basename($this->invoicePath),
                'mime' => 'application/pdf',
            ]);
            Log::info('Attached standard invoice PDF.', ['filename' => basename($this->invoicePath)]);
        } else {
            Log::error('Standard invoice PDF not found, cannot attach.', ['path' => $this->invoicePath]);
        }

        // Attach fiscal PDF if exists
        if ($this->fiscalPath && Storage::disk('public')->exists($this->fiscalPath)) {
            $mail->attach(Storage::disk('public')->path($this->fiscalPath), [
                'as' => basename($this->fiscalPath),
                'mime' => 'application/pdf',
            ]);
            Log::info('Attached fiscal invoice PDF.', ['filename' => basename($this->fiscalPath)]);
        } else {
            Log::warning('Fiscal invoice PDF not found or path is null, skipping attachment.', ['path' => $this->fiscalPath ?? 'N/A']);
        }

        return $mail;
    }
}
