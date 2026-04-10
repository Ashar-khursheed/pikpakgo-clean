@extends('emails.layout')
@php $recipientEmail = $booking->holder_email; @endphp

@section('email_title', 'How was your stay at ' . $booking->property_name . '?')

@section('body')
<div class="greeting">We hope you had a great stay!</div>
<p class="intro-text">
  Hi <strong>{{ $booking->holder_first_name }}</strong>, you recently stayed at
  <strong>{{ $booking->property_name }}</strong>. We'd love to hear about your experience.
  Your review helps other travellers make the right choice.
</p>

<div class="info-card">
  <h3>🏡 Your Recent Stay</h3>
  <div class="info-row">
    <span class="info-label">Property</span>
    <span class="info-value">{{ $booking->property_name }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Location</span>
    <span class="info-value">{{ $booking->property_city }}{{ $booking->property_country ? ', ' . $booking->property_country : '' }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Stay Dates</span>
    <span class="info-value">
      {{ \Carbon\Carbon::parse($booking->check_in_date)->format('d M Y') }} –
      {{ \Carbon\Carbon::parse($booking->check_out_date)->format('d M Y') }}
    </span>
  </div>
  <div class="info-row">
    <span class="info-label">Booking Ref</span>
    <span class="info-value">{{ $booking->booking_reference }}</span>
  </div>
</div>

<!-- Star Rating Visual -->
<div style="text-align:center;margin:24px 0;">
  <p style="font-size:15px;color:#555;margin-bottom:8px;">How would you rate your overall experience?</p>
  <div style="font-size:36px;letter-spacing:4px;">⭐⭐⭐⭐⭐</div>
</div>

<div class="cta-wrap">
  <a href="{{ config('app.url') }}/reviews/create?booking={{ $booking->booking_reference }}" class="cta-btn">
    Write Your Review
  </a>
</div>

<p style="text-align:center;color:#888;font-size:13px;margin-top:8px;">
  This review link is only for you. It expires in 30 days.
</p>

<hr class="divider">

<div class="alert-box info">
  <strong>Privacy note:</strong> Your review will be published under your first name only.
  If you have any concerns about your stay, please contact us at
  <a href="mailto:support@pikpakgo.com">support@pikpakgo.com</a> before submitting a review.
</div>
@endsection
