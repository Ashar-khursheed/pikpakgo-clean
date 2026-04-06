@extends('emails.layout')
@php $recipientEmail = $booking->holder_email; @endphp

@section('email_title', 'Booking Confirmed — ' . $booking->booking_reference)

@section('body')
<div class="greeting">Booking Confirmed! 🎉</div>
<p class="intro-text">
  Hi <strong>{{ $booking->holder_first_name }}</strong>, great news! Your booking has been confirmed.
  Get ready for an amazing stay. Here are your booking details:
</p>

<span class="status-badge confirmed">✓ Confirmed</span>

<!-- Dates -->
<div class="dates-strip">
  <div class="date-box">
    <div class="date-label">Check-In</div>
    <div class="date-val">{{ \Carbon\Carbon::parse($booking->check_in_date)->format('d M Y') }}</div>
    <div class="date-day">{{ \Carbon\Carbon::parse($booking->check_in_date)->format('l') }}</div>
  </div>
  <div class="date-box">
    <div class="date-label">Check-Out</div>
    <div class="date-val">{{ \Carbon\Carbon::parse($booking->check_out_date)->format('d M Y') }}</div>
    <div class="date-day">{{ \Carbon\Carbon::parse($booking->check_out_date)->format('l') }}</div>
  </div>
  <div class="date-box">
    <div class="date-label">Duration</div>
    <div class="date-val">{{ $booking->nights }}</div>
    <div class="date-day">Night{{ $booking->nights > 1 ? 's' : '' }}</div>
  </div>
</div>

<!-- Property Info -->
<div class="info-card">
  <h3>🏡 Property Details</h3>
  <div class="info-row">
    <span class="info-label">Property</span>
    <span class="info-value">{{ $booking->property_name }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Location</span>
    <span class="info-value">{{ $booking->property_city }}{{ $booking->property_country ? ', ' . $booking->property_country : '' }}</span>
  </div>
  @if($booking->property_address)
  <div class="info-row">
    <span class="info-label">Address</span>
    <span class="info-value">{{ $booking->property_address }}</span>
  </div>
  @endif
  <div class="info-row">
    <span class="info-label">Guests</span>
    <span class="info-value">{{ $booking->total_adults }} adult{{ $booking->total_adults > 1 ? 's' : '' }}{{ $booking->total_children ? ', ' . $booking->total_children . ' child' . ($booking->total_children > 1 ? 'ren' : '') : '' }}</span>
  </div>
</div>

<!-- Booking Info -->
<div class="info-card">
  <h3>📋 Booking Summary</h3>
  <div class="info-row">
    <span class="info-label">Booking Reference</span>
    <span class="info-value" style="color:#1a73e8;font-size:16px;">{{ $booking->booking_reference }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Guest Name</span>
    <span class="info-value">{{ $booking->holder_first_name }} {{ $booking->holder_last_name }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Contact Email</span>
    <span class="info-value">{{ $booking->holder_email }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Contact Phone</span>
    <span class="info-value">{{ $booking->holder_phone }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Confirmed On</span>
    <span class="info-value">{{ now()->format('d M Y, h:i A') }}</span>
  </div>
</div>

<!-- Total -->
<div class="total-box">
  <div>
    <div class="total-label">Total Paid</div>
    <div class="total-sub">{{ $booking->nights }} nights &bull; All taxes included</div>
  </div>
  <div>
    <div class="total-amount">{{ $booking->currency }} {{ number_format($booking->total_price, 2) }}</div>
  </div>
</div>

@if($booking->special_requests)
<div class="alert-box info">
  <strong>Your Special Requests:</strong><br>
  {{ $booking->special_requests }}
</div>
@endif

@if($booking->free_cancellation_until)
<div class="alert-box success">
  <strong>Free Cancellation:</strong> You can cancel for free until
  <strong>{{ \Carbon\Carbon::parse($booking->free_cancellation_until)->format('d M Y') }}</strong>.
</div>
@endif

<div class="cta-wrap">
  <a href="{{ config('app.url') }}/bookings/{{ $booking->booking_reference }}" class="cta-btn">
    View Booking Details
  </a>
</div>

<hr class="divider">

<div class="alert-box warning">
  <strong>Need help?</strong> Contact our support team anytime at
  <a href="mailto:support@pikpakgo.com">support@pikpakgo.com</a> and reference your booking
  number <strong>{{ $booking->booking_reference }}</strong>.
</div>
@endsection
