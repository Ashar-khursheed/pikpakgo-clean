@extends('emails.layout')
@php $recipientEmail = $booking->holder_email; @endphp

@section('email_title', 'Booking Cancelled — ' . $booking->booking_reference)

@section('body')
<div class="greeting">Booking Cancelled</div>
<p class="intro-text">
  Hi <strong>{{ $booking->holder_first_name }}</strong>, we're sorry to inform you that your booking
  has been cancelled. Here are the details:
</p>

<span class="status-badge cancelled">✗ Cancelled</span>

<div class="info-card">
  <h3>📋 Cancelled Booking</h3>
  <div class="info-row">
    <span class="info-label">Booking Reference</span>
    <span class="info-value" style="color:#e53935;font-size:16px;">{{ $booking->booking_reference }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Property</span>
    <span class="info-value">{{ $booking->property_name }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Check-In</span>
    <span class="info-value">{{ \Carbon\Carbon::parse($booking->check_in_date)->format('d M Y') }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Check-Out</span>
    <span class="info-value">{{ \Carbon\Carbon::parse($booking->check_out_date)->format('d M Y') }}</span>
  </div>
  @if($booking->cancellation_reason)
  <div class="info-row">
    <span class="info-label">Reason</span>
    <span class="info-value">{{ $booking->cancellation_reason }}</span>
  </div>
  @endif
</div>

@if($refundAmount > 0)
<div class="total-box">
  <div>
    <div class="total-label">Refund Amount</div>
    <div class="total-sub">Will be processed within 5–10 business days</div>
  </div>
  <div>
    <div class="total-amount">{{ $booking->currency }} {{ number_format($refundAmount, 2) }}</div>
  </div>
</div>

<div class="alert-box success">
  <strong>Refund initiated.</strong> Your refund of <strong>{{ $booking->currency }} {{ number_format($refundAmount, 2) }}</strong>
  will be credited to your original payment method within 5–10 business days.
</div>
@else
<div class="alert-box warning">
  <strong>No refund applicable</strong> based on the cancellation policy for this booking.
</div>
@endif

<div class="cta-wrap">
  <a href="{{ config('app.url') }}/properties" class="cta-btn">
    Browse Other Properties
  </a>
</div>

<hr class="divider">

<div class="alert-box info">
  <strong>Need help?</strong> If you believe this cancellation was made in error, please contact us at
  <a href="mailto:support@pikpakgo.com">support@pikpakgo.com</a> with your booking reference
  <strong>{{ $booking->booking_reference }}</strong>.
</div>
@endsection
