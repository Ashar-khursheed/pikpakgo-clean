<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
  .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  .header { background: #34a853; color: #fff; padding: 30px; text-align: center; }
  .body { padding: 30px; }
  .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
  .detail-label { color: #666; font-size: 14px; }
  .detail-value { font-weight: bold; font-size: 14px; }
  .amount-box { background: #f9f9f9; padding: 20px; border-radius: 6px; text-align: center; margin: 20px 0; }
  .amount-box .amount { font-size: 28px; color: #34a853; font-weight: bold; }
  .footer { background: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Payment Receipt</h1>
  </div>
  <div class="body">
    <p>Dear {{ $booking->holder_first_name }},</p>
    <p>We have received your payment. Here is your receipt:</p>

    <div class="detail-row">
      <span class="detail-label">Transaction ID</span>
      <span class="detail-value">{{ $transaction->transaction_id }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Booking Reference</span>
      <span class="detail-value">{{ $booking->booking_reference }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Property</span>
      <span class="detail-value">{{ $booking->property_name }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Payment Method</span>
      <span class="detail-value">{{ ucfirst($transaction->payment_method ?? 'Card') }}</span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Date</span>
      <span class="detail-value">{{ $transaction->processed_at ? \Carbon\Carbon::parse($transaction->processed_at)->format('M d, Y H:i') : now()->format('M d, Y H:i') }}</span>
    </div>

    <div class="amount-box">
      <div style="color:#666; font-size:14px; margin-bottom:5px;">Amount Paid</div>
      <div class="amount">{{ $transaction->currency }} {{ number_format($transaction->amount, 2) }}</div>
    </div>

    <p>Keep this receipt for your records. Your booking is now confirmed.</p>
  </div>
  <div class="footer">&copy; {{ date('Y') }} PikPakGo. All rights reserved.</div>
</div>
</body>
</html>
