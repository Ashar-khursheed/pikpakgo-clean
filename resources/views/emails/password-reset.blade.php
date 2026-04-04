<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
  .container { max-width: 580px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  .header { background: #ea4335; color: #fff; padding: 30px; text-align: center; }
  .body { padding: 30px; }
  .btn { display: inline-block; background: #1a73e8; color: #fff; padding: 13px 28px; border-radius: 6px; text-decoration: none; font-size: 15px; margin: 20px 0; }
  .token-box { background: #f4f4f4; padding: 12px 18px; border-radius: 6px; font-family: monospace; font-size: 16px; word-break: break-all; margin: 12px 0; }
  .footer { background: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="container">
  <div class="header"><h1>Reset Your Password</h1></div>
  <div class="body">
    <p>Hi {{ $user->first_name }},</p>
    <p>You requested a password reset. Use the token below (valid for 1 hour):</p>
    <div class="token-box">{{ $token }}</div>
    <p>Submit this token via the API:<br>
       <code>POST /api/auth/reset-password</code><br>
       with <code>email</code>, <code>token</code>, <code>password</code>, and <code>password_confirmation</code>.
    </p>
    <p>If you did not request a password reset, ignore this email.</p>
  </div>
  <div class="footer">&copy; {{ date('Y') }} PikPakGo. This link expires in 1 hour.</div>
</div>
</body>
</html>
