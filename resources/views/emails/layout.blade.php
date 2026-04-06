<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>@yield('email_title', 'PikPakGo')</title>
  <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f0f4f8; color: #1a202c; -webkit-font-smoothing: antialiased; }
    .email-wrapper { background-color: #f0f4f8; padding: 40px 16px; }
    .email-card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }

    /* Header */
    .email-header { background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%); padding: 36px 40px; text-align: center; }
    .email-header .logo { font-size: 28px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
    .email-header .logo span { color: #90caf9; }
    .email-header .tagline { color: rgba(255,255,255,0.75); font-size: 13px; margin-top: 4px; }

    /* Body */
    .email-body { padding: 40px; }
    .greeting { font-size: 22px; font-weight: 700; color: #1a202c; margin-bottom: 12px; }
    .intro-text { font-size: 15px; color: #4a5568; line-height: 1.7; margin-bottom: 28px; }

    /* Status badge */
    .status-badge { display: inline-block; padding: 6px 16px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 24px; }
    .status-badge.confirmed  { background: #c6f6d5; color: #22543d; }
    .status-badge.pending    { background: #fefcbf; color: #744210; }
    .status-badge.cancelled  { background: #fed7d7; color: #742a2a; }

    /* Info card */
    .info-card { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    .info-card h3 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #718096; margin-bottom: 16px; }
    .info-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid #edf2f7; }
    .info-row:last-child { border-bottom: none; padding-bottom: 0; }
    .info-label { font-size: 13px; color: #718096; flex: 1; }
    .info-value { font-size: 14px; font-weight: 600; color: #2d3748; text-align: right; flex: 1; }

    /* Dates strip */
    .dates-strip { display: flex; gap: 16px; margin-bottom: 24px; }
    .date-box { flex: 1; background: #ebf8ff; border: 1px solid #bee3f8; border-radius: 12px; padding: 16px; text-align: center; }
    .date-box .date-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #2b6cb0; margin-bottom: 6px; }
    .date-box .date-val { font-size: 18px; font-weight: 800; color: #1a365d; }
    .date-box .date-day { font-size: 12px; color: #4a5568; margin-top: 2px; }

    /* Total */
    .total-box { background: linear-gradient(135deg, #ebf8ff, #e6fffa); border: 2px solid #90cdf4; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
    .total-box .total-label { font-size: 15px; font-weight: 600; color: #2d3748; }
    .total-box .total-amount { font-size: 28px; font-weight: 800; color: #1a73e8; }
    .total-box .total-sub { font-size: 12px; color: #718096; margin-top: 2px; }

    /* CTA Button */
    .cta-wrap { text-align: center; margin: 28px 0; }
    .cta-btn { display: inline-block; background: linear-gradient(135deg, #1a73e8, #0d47a1); color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 15px; padding: 14px 36px; border-radius: 999px; letter-spacing: 0.3px; }

    /* Alert box */
    .alert-box { border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
    .alert-box.info    { background: #ebf8ff; border-left: 4px solid #1a73e8; color: #2b6cb0; }
    .alert-box.success { background: #f0fff4; border-left: 4px solid #38a169; color: #276749; }
    .alert-box.warning { background: #fffbeb; border-left: 4px solid #d69e2e; color: #744210; }

    /* Divider */
    .divider { border: none; border-top: 1px solid #edf2f7; margin: 28px 0; }

    /* Footer */
    .email-footer { background: #f7fafc; border-top: 1px solid #edf2f7; padding: 28px 40px; text-align: center; }
    .email-footer p { font-size: 12px; color: #a0aec0; line-height: 1.8; }
    .email-footer a { color: #1a73e8; text-decoration: none; }
    .social-links { margin: 12px 0; }
    .social-links a { display: inline-block; margin: 0 6px; width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; line-height: 32px; text-align: center; color: #4a5568; font-size: 13px; text-decoration: none; }

    @media (max-width: 480px) {
      .email-body { padding: 24px 20px; }
      .dates-strip { flex-direction: column; }
      .total-box { flex-direction: column; text-align: center; gap: 8px; }
    }
  </style>
</head>
<body>
<div class="email-wrapper">
  <div class="email-card">

    <!-- Header -->
    <div class="email-header">
      <div class="logo">Pik<span>Pak</span>Go</div>
      <div class="tagline">Your Premium Vacation Rental Platform</div>
    </div>

    <!-- Body -->
    <div class="email-body">
      @yield('body')
    </div>

    <!-- Footer -->
    <div class="email-footer">
      <div class="social-links">
        <a href="#">f</a>
        <a href="#">in</a>
        <a href="#">tw</a>
        <a href="#">ig</a>
      </div>
      <p>
        &copy; {{ date('Y') }} PikPakGo. All rights reserved.<br>
        <a href="#">Privacy Policy</a> &bull; <a href="#">Terms of Service</a> &bull; <a href="#">Contact Support</a>
      </p>
      <p style="margin-top:8px;">
        This email was sent to {{ $recipientEmail ?? 'you' }}.<br>
        If you did not request this, please ignore this email.
      </p>
    </div>

  </div>
</div>
</body>
</html>
