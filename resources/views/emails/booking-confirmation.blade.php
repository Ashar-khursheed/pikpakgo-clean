<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
  .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  .header { background: #1a73e8; color: #fff; padding: 30px; text-align: center; }
  .header h1 { margin: 0; font-size: 24px; }
  .body { padding: 30px; }
  .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
  .detail-label { color: #666; font-size: 14px; }
  .detail-value { font-weight: bold; font-size: 14px; }
  .total { background: #f9f9f9; padding: 15px; border-radius: 6px; margin-top: 20px; }
  .total .amount { font-size: 22px; color: #1a73e8; font-weight: bold; }
  .footer { background: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; color: #999; }
  .badge { display: inline-block; background: #34a853; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 13px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Booking Confirmed!</h1>
    <p style="margin:8px 0 0; opacity:.9;">Reference: {{ $booking->booking_reference }}</p>
  </div>
  <div class="body">
    <p>Dear {{ $booking->holder_first_name }},</p>
    <p>Your booking is confirmed. Here are your details:</p>

    <div class="detail-row">
      <span class="detail-label">Property</span>
      <span class="detail-value">{{ $booking->property_name }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Location</span>
      <span class="detail-value">{{ $booking->property_city }}{{ $booking->property_country ? ', '.$booking->property_country : '' }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Check-in</span>
      <span class="detail-value">{{ \Carbon\Carbon::parse($booking->check_in_date)->format('D, M d, Y') }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Check-out</span>
      <span class="detail-value">{{ \Carbon\Carbon::parse($booking->check_out_date)->format('D, M d, Y') }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Nights</span>
      <span class="detail-value">{{ $booking->nights }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Guests</span>
      <span class="detail-value">{{ $booking->total_adults }} adults{{ $booking->total_children ? ', '.$booking->total_children.' children' : '' }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Status</span>
      <span class="detail-value"><span class="badge">Confirmed</span></span>
    </div>

    <div class="total">
      <div class="detail-label">Total Paid</div>
      <div class="amount">{{ $booking->currency }} {{ number_format($booking->total_price, 2) }}</div>
    </div>

    <p style="margin-top:25px;">If you have any questions, reply to this email or contact our support team.</p>
    <p>Thank you for choosing PikPakGo!</p>
  </div>
  <div class="footer">
    &copy; {{ date('Y') }} PikPakGo. All rights reserved.
  </div>
</div>
</body>
</html>
