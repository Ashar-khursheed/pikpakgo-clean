@extends('emails.layout')
@php $recipientEmail = $user->email; @endphp

@section('email_title', 'Verify Your Email — PikPakGo')

@section('body')
<div class="greeting">Welcome to PikPakGo! 🎉</div>
<p class="intro-text">
  Hi <strong>{{ $user->first_name }}</strong>, thanks for signing up!
  You're just one step away from discovering amazing vacation rentals.
  Please verify your email address to activate your account.
</p>

<div class="cta-wrap">
  <a href="{{ config('app.url') }}/verify-email/{{ $token }}" class="cta-btn">
    Verify My Email Address
  </a>
</div>

<div class="info-card">
  <h3>👤 Your Account Details</h3>
  <div class="info-row">
    <span class="info-label">Full Name</span>
    <span class="info-value">{{ $user->first_name }} {{ $user->last_name }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Email Address</span>
    <span class="info-value">{{ $user->email }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Account Type</span>
    <span class="info-value" style="text-transform:capitalize;">{{ $user->user_type }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Registered On</span>
    <span class="info-value">{{ now()->format('d M Y') }}</span>
  </div>
</div>

<p style="font-size:13px;color:#718096;text-align:center;margin-top:16px;">
  If the button doesn't work, copy and paste this link:<br>
  <span style="font-size:11px;word-break:break-all;color:#1a73e8;">
    {{ config('app.url') }}/verify-email/{{ $token }}
  </span>
</p>

<hr class="divider">

<div style="text-align:center;">
  <p style="font-size:14px;color:#4a5568;margin-bottom:16px;">
    Once verified, you'll be able to:
  </p>
  <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
    <div style="background:#f7fafc;border-radius:10px;padding:16px;flex:1;min-width:140px;text-align:center;">
      <div style="font-size:24px;margin-bottom:8px;">🏡</div>
      <div style="font-size:13px;font-weight:600;color:#2d3748;">Browse Properties</div>
    </div>
    <div style="background:#f7fafc;border-radius:10px;padding:16px;flex:1;min-width:140px;text-align:center;">
      <div style="font-size:24px;margin-bottom:8px;">📅</div>
      <div style="font-size:13px;font-weight:600;color:#2d3748;">Book Instantly</div>
    </div>
    <div style="background:#f7fafc;border-radius:10px;padding:16px;flex:1;min-width:140px;text-align:center;">
      <div style="font-size:24px;margin-bottom:8px;">❤️</div>
      <div style="font-size:13px;font-weight:600;color:#2d3748;">Save Favourites</div>
    </div>
  </div>
</div>
@endsection
