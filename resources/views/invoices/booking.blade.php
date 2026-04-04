<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; color: #333; padding: 40px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 3px solid #1a73e8; padding-bottom: 20px; }
  .logo { font-size: 26px; font-weight: bold; color: #1a73e8; }
  .logo span { color: #ea4335; }
  .invoice-meta { text-align: right; }
  .invoice-meta h2 { font-size: 22px; color: #1a73e8; margin-bottom: 6px; }
  .invoice-meta p { color: #666; font-size: 12px; }
  .parties { display: flex; justify-content: space-between; margin-bottom: 30px; }
  .party h4 { font-size: 11px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 8px; }
  .party p { line-height: 1.7; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
  thead { background: #1a73e8; color: #fff; }
  thead th { padding: 10px 14px; text-align: left; font-size: 12px; font-weight: 600; }
  tbody tr { border-bottom: 1px solid #eee; }
  tbody tr:nth-child(even) { background: #f9f9f9; }
  tbody td { padding: 10px 14px; font-size: 13px; }
  .totals { float: right; width: 260px; }
  .totals table td { padding: 6px 10px; }
  .totals .grand-total td { font-size: 15px; font-weight: bold; color: #1a73e8; border-top: 2px solid #1a73e8; }
  .status-badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
  .status-confirmed { background: #e6f4ea; color: #34a853; }
  .status-pending { background: #fef7e0; color: #f9ab00; }
  .status-cancelled { background: #fce8e6; color: #ea4335; }
  .footer { margin-top: 60px; border-top: 1px solid #eee; padding-top: 15px; font-size: 11px; color: #999; text-align: center; }
  .clearfix::after { content: ''; display: table; clear: both; }
</style>
</head>
<body>

<div class="header">
  <div class="logo">Pik<span>Pak</span>Go</div>
  <div class="invoice-meta">
    <h2>INVOICE</h2>
    <p><strong>Ref:</strong> {{ $booking->booking_reference }}</p>
    <p><strong>Date:</strong> {{ now()->format('M d, Y') }}</p>
    <p>
      <span class="status-badge status-{{ $booking->booking_status }}">
        {{ ucfirst($booking->booking_status) }}
      </span>
    </p>
  </div>
</div>

<div class="parties">
  <div class="party">
    <h4>Billed To</h4>
    <p>
      <strong>{{ $booking->holder_first_name }} {{ $booking->holder_last_name }}</strong><br>
      {{ $booking->holder_email }}<br>
      {{ $booking->holder_phone ?? '' }}
    </p>
  </div>
  <div class="party" style="text-align:right;">
    <h4>Property</h4>
    <p>
      <strong>{{ $booking->property_name }}</strong><br>
      {{ $booking->property_city }}{{ $booking->property_country ? ', '.$booking->property_country : '' }}<br>
      Provider: {{ ucfirst($booking->provider) }}
    </p>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Description</th>
      <th>Check-in</th>
      <th>Check-out</th>
      <th>Nights</th>
      <th>Guests</th>
      <th style="text-align:right;">Amount</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>{{ $booking->property_name }}<br><small style="color:#999;">Accommodation</small></td>
      <td>{{ \Carbon\Carbon::parse($booking->check_in_date)->format('M d, Y') }}</td>
      <td>{{ \Carbon\Carbon::parse($booking->check_out_date)->format('M d, Y') }}</td>
      <td>{{ $booking->nights }}</td>
      <td>{{ $booking->total_adults }}A {{ $booking->total_children ? '+ '.$booking->total_children.'C' : '' }}</td>
      <td style="text-align:right;">{{ $booking->currency }} {{ number_format($booking->base_price, 2) }}</td>
    </tr>
    @if($booking->markup_amount > 0)
    <tr>
      <td colspan="5">Service Fee</td>
      <td style="text-align:right;">{{ $booking->currency }} {{ number_format($booking->markup_amount, 2) }}</td>
    </tr>
    @endif
    @if($booking->tax_amount > 0)
    <tr>
      <td colspan="5">Taxes &amp; Fees</td>
      <td style="text-align:right;">{{ $booking->currency }} {{ number_format($booking->tax_amount, 2) }}</td>
    </tr>
    @endif
  </tbody>
</table>

<div class="clearfix">
  <div class="totals">
    <table>
      <tr>
        <td>Subtotal</td>
        <td style="text-align:right;">{{ $booking->currency }} {{ number_format($booking->base_price, 2) }}</td>
      </tr>
      @if($booking->markup_amount > 0)
      <tr>
        <td>Service Fee</td>
        <td style="text-align:right;">{{ $booking->currency }} {{ number_format($booking->markup_amount, 2) }}</td>
      </tr>
      @endif
      @if($booking->tax_amount > 0)
      <tr>
        <td>Tax</td>
        <td style="text-align:right;">{{ $booking->currency }} {{ number_format($booking->tax_amount, 2) }}</td>
      </tr>
      @endif
      <tr class="grand-total">
        <td><strong>Total</strong></td>
        <td style="text-align:right;"><strong>{{ $booking->currency }} {{ number_format($booking->total_price, 2) }}</strong></td>
      </tr>
      <tr>
        <td style="color:#34a853;">Paid</td>
        <td style="text-align:right; color:#34a853;">{{ $booking->currency }} {{ number_format($booking->paid_amount, 2) }}</td>
      </tr>
    </table>
  </div>
</div>

@if($booking->special_requests)
<div style="margin-top:30px; padding:12px; background:#f9f9f9; border-radius:6px; font-size:12px;">
  <strong>Special Requests:</strong> {{ $booking->special_requests }}
</div>
@endif

<div class="footer">
  PikPakGo — Your Premier Vacation Rental Platform<br>
  For support: support@pikpakgo.com &nbsp;|&nbsp; Generated: {{ now()->format('M d, Y H:i') }}
</div>

</body>
</html>
