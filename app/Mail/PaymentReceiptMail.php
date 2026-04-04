<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public PaymentTransaction $transaction
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Receipt — ' . $this->booking->booking_reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-receipt',
        );
    }
}
