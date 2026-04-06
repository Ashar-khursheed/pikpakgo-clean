@extends('emails.layout')
@php $recipientEmail = $booking->holder_email; @endphp

@section('email_title', 'Payment Receipt — ' . $booking->booking_reference)

@section('body')
<div class="greeting">Payment Received ✅</div>
<p class="intro-text">
  Hi <strong>{{ $booking->holder_first_name }}</strong>, your payment has been successfully processed.
  This is your official payment receipt. Please keep it for your records.
</p>

<span class="status-badge confirmed">✓ Payment Successful</span>

<!-- Transaction Info -->
<div class="info-card">
  <h3>💳 Transaction Details</h3>
  <div class="info-row">
    <span class="info-label">Transaction ID</span>
    <span class="info-value" style="font-family:monospace;font-size:12px;color:#1a73e8;">{{ $transaction->transaction_id }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Payment Method</span>
    <span class="info-value">
      {{ $transaction->card_brand ?? 'Card' }}
      @if($transaction->card_last_four) &bull;&bull;&bull;&bull; {{ $transaction->card_last_four }} @endif
    </span>
  </div>
  <div class="info-row">
    <span class="info-label">Payment Date</span>
    <span class="info-value">{{ $transaction->processed_at ? \Carbon\Carbon::parse($transaction->processed_at)->format('d M Y, h:i A') : now()->format('d M Y, h:i A') }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Gateway</span>
    <span class="info-value">Authorize.Net (Secure)</span>
  </div>
</div>

<!-- Booking Reference -->
<div class="info-card">
  <h3>📋 Booking Reference</h3>
  <div class="info-row">
    <span class="info-label">Booking ID</span>
    <span class="info-value" style="color:#1a73e8;font-size:16px;">{{ $booking->booking_reference }}</span>
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
  <div class="info-row">
    <span class="info-label">Duration</span>
    <span class="info-value">{{ $booking->nights }} Night{{ $booking->nights > 1 ? 's' : '' }}</span>
  </div>
</div>

<!-- Price Breakdown -->
<div class="info-card">
  <h3>💰 Price Breakdown</h3>
  <div class="info-row">
    <span class="info-label">Base Price ({{ $booking->nights }} nights)</span>
    <span class="info-value">{{ $booking->currency }} {{ number_format($booking->base_price, 2) }}</span>
  </div>
  @if($booking->markup_amount > 0)
  <div class="info-row">
    <span class="info-label">Service Fee</span>
    <span class="info-value">{{ $booking->currency }} {{ number_format($booking->markup_amount, 2) }}</span>
  </div>
  @endif
  @if($booking->tax_amount > 0)
  <div class="info-row">
    <span class="info-label">Taxes</span>
    <span class="info-value">{{ $booking->currency }} {{ number_format($booking->tax_amount, 2) }}</span>
  </div>
  @endif
</div>

<!-- Total -->
<div class="total-box">
  <div>
    <div class="total-label">Amount Charged</div>
    <div class="total-sub">Paid in full &bull; PCI-DSS Secured</div>
  </div>
  <div>
    <div class="total-amount">{{ $booking->currency }} {{ number_format($booking->paid_amount ?? $booking->total_price, 2) }}</div>
  </div>
</div>

<div class="cta-wrap">
  <a href="{{ config('app.url') }}/bookings/{{ $booking->booking_reference }}/invoice" class="cta-btn">
    Download PDF Invoice
  </a>
</div>

<hr class="divider">

<div class="alert-box info">
  <strong>🔒 Secure Payment:</strong> Your payment was processed securely through Authorize.Net,
  a PCI-DSS Level 1 certified payment processor. Your card details are never stored on our servers.
</div>
@endsection
