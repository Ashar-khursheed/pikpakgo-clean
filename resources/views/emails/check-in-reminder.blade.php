@extends('emails.layout')
@php $recipientEmail = $booking->holder_email; @endphp

@section('email_title', 'Your check-in is tomorrow — ' . $booking->property_name)

@section('body')
<div class="greeting">Your stay starts tomorrow!</div>
<p class="intro-text">
  Hi <strong>{{ $booking->holder_first_name }}</strong>, just a friendly reminder that your check-in
  is <strong>tomorrow</strong>. We hope you're excited for your stay!
</p>

<!-- Dates Strip -->
<div class="dates-strip">
  <div class="date-box">
    <div class="date-label">Check-In</div>
    <div class="date-val">{{ \Carbon\Carbon::parse($booking->check_in_date)->format('d M Y') }}</div>
    <div class="date-day">Tomorrow</div>
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
  <h3>🏡 Your Property</h3>
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

<div class="info-card">
  <h3>📋 Booking Reference</h3>
  <div class="info-row">
    <span class="info-label">Reference</span>
    <span class="info-value" style="color:#1a73e8;font-size:16px;">{{ $booking->booking_reference }}</span>
  </div>
  @if($booking->property_latitude && $booking->property_longitude)
  <div class="info-row">
    <span class="info-label">Map</span>
    <span class="info-value">
      <a href="https://maps.google.com/?q={{ $booking->property_latitude }},{{ $booking->property_longitude }}" style="color:#1a73e8;">
        Open in Google Maps
      </a>
    </span>
  </div>
  @endif
</div>

@if($booking->special_requests)
<div class="alert-box info">
  <strong>Your Special Requests:</strong><br>
  {{ $booking->special_requests }}
</div>
@endif

<div class="cta-wrap">
  <a href="{{ config('app.url') }}/bookings/{{ $booking->booking_reference }}" class="cta-btn">
    View Booking Details
  </a>
</div>

<hr class="divider">

<div class="alert-box info">
  <strong>Need help?</strong> Contact us at <a href="mailto:support@pikpakgo.com">support@pikpakgo.com</a>
  or reply to this email. We're here 24/7.
</div>
@endsection
