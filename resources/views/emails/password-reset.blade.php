@extends('emails.layout')
@php $recipientEmail = $user->email; @endphp

@section('email_title', 'Reset Your Password — PikPakGo')

@section('body')
<div class="greeting">Password Reset Request 🔐</div>
<p class="intro-text">
  Hi <strong>{{ $user->first_name }}</strong>, we received a request to reset the password
  for your PikPakGo account. Click the button below to create a new password.
</p>

<div class="alert-box warning">
  <strong>⏰ This link expires in 60 minutes.</strong><br>
  If you don't reset your password within this time, you'll need to submit a new request.
</div>

<div class="cta-wrap">
  <a href="{{ config('app.url') }}/reset-password?token={{ $token }}&email={{ urlencode($user->email) }}" class="cta-btn">
    Reset My Password
  </a>
</div>

<div class="info-card">
  <h3>🛡️ Security Information</h3>
  <div class="info-row">
    <span class="info-label">Account Email</span>
    <span class="info-value">{{ $user->email }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Request Time</span>
    <span class="info-value">{{ now()->format('d M Y, h:i A') }}</span>
  </div>
  <div class="info-row">
    <span class="info-label">Link Expires</span>
    <span class="info-value">{{ now()->addMinutes(60)->format('d M Y, h:i A') }}</span>
  </div>
</div>

<p style="font-size:13px;color:#718096;text-align:center;margin-top:16px;">
  If the button doesn't work, copy and paste this link into your browser:<br>
  <span style="font-size:11px;word-break:break-all;color:#1a73e8;">
    {{ config('app.url') }}/reset-password?token={{ $token }}&email={{ urlencode($user->email) }}
  </span>
</p>

<hr class="divider">

<div class="alert-box info">
  <strong>Didn't request this?</strong> If you did not request a password reset, your account is safe.
  Simply ignore this email — no changes will be made. If you're concerned about your account security,
  please contact us at <a href="mailto:security@pikpakgo.com">security@pikpakgo.com</a>.
</div>
@endsection
