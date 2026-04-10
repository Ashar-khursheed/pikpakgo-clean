<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public float $refundAmount = 0
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Cancelled — ' . $this->booking->booking_reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-cancellation',
            with: [
                'refundAmount' => $this->refundAmount,
            ],
        );
    }
}
