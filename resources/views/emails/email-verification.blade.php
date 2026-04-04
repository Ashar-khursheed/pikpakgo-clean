<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
  .container { max-width: 580px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  .header { background: #1a73e8; color: #fff; padding: 30px; text-align: center; }
  .body { padding: 30px; }
  .token-box { background: #f4f4f4; padding: 12px 18px; border-radius: 6px; font-family: monospace; font-size: 16px; word-break: break-all; margin: 12px 0; }
  .footer { background: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="container">
  <div class="header"><h1>Verify Your Email</h1></div>
  <div class="body">
    <p>Hi {{ $user->first_name }}, welcome to PikPakGo!</p>
    <p>Please verify your email address by calling:</p>
    <code>POST /api/auth/verify-email/{{ $token }}</code>
    <p>Or use the verification token below:</p>
    <div class="token-box">{{ $token }}</div>
    <p>If you did not create an account, no action is required.</p>
  </div>
  <div class="footer">&copy; {{ date('Y') }} PikPakGo.</div>
</div>
</body>
</html>
