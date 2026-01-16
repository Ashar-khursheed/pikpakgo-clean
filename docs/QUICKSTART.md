# Quick Start Guide - PikPakGo

## ⚡ 5-Minute Setup

### Step 1: Copy Files (2 minutes)

```bash
cd /path/to/your-laravel-project

# Copy all files
cp -r /path/to/pikpakgo-complete/database/migrations/* database/migrations/
cp -r /path/to/pikpakgo-complete/app/Http/Controllers/* app/Http/Controllers/
cp -r /path/to/pikpakgo-complete/app/Models/* app/Models/
cp -r /path/to/pikpakgo-complete/app/Services/* app/Services/

# Backup and replace routes
cp routes/api.php routes/api.php.backup
cp /path/to/pikpakgo-complete/routes/api.php routes/api.php
```

### Step 2: Run Migrations (1 minute)

```bash
php artisan migrate
```

### Step 3: Seed Default Markup (1 minute)

```bash
php artisan tinker
```

```php
\App\Models\PricingMarkup::create([
    'name' => 'Default 15% Markup',
    'markup_type' => 'percentage',
    'markup_percentage' => 15,
    'provider' => 'all',
    'is_active' => true,
    'is_default' => true,
    'priority' => 0
]);

exit;
```

### Step 4: Test It! (1 minute)

```bash
# Test public search (no auth needed)
curl -X POST http://localhost:8000/public/search/hotels \
  -H "Content-Type: application/json" \
  -d '{
    "checkIn": "2025-03-01",
    "checkOut": "2025-03-03",
    "destination": "NYC",
    "adults": 2
  }'
```

## 🎯 First API Call

### Test Public Search

```javascript
const response = await fetch('http://localhost:8000/public/search/hotels', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    checkIn: '2025-03-01',
    checkOut: '2025-03-03',
    destination: 'NYC',
    adults: 2,
    children: 0,
    rooms: 1
  })
});

const data = await response.json();
console.log(data);
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "total": 150,
    "hotels": [
      {
        "hotel_code": "12345",
        "name": "Grand Hotel NYC",
        "pricing": {
          "base_price": 200,
          "markup_amount": 30,
          "markup_percentage": 15,
          "final_price": 230,
          "currency": "USD"
        },
        "city": "New York",
        "rating": 4.5
      }
    ]
  }
}
```

## 🔥 Complete Booking Flow (No Login Required)

### 1. Create Guest Session

```javascript
const sessionRes = await fetch('/guest/session/create', {
  method: 'POST'
});
const { data } = await sessionRes.json();
const sessionId = data.session_id;
```

### 2. Search Hotels

```javascript
const searchRes = await fetch('/public/search/hotels', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    checkIn: '2025-03-01',
    checkOut: '2025-03-03',
    destination: 'NYC',
    adults: 2
  })
});
const hotels = await searchRes.json();
```

### 3. Create Booking

```javascript
const bookingRes = await fetch('/bookings/guest/create', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    guest_session_id: sessionId,
    provider: 'hotelbeds',
    property_code: hotels.data.hotels[0].hotel_code,
    check_in_date: '2025-03-01',
    check_out_date: '2025-03-03',
    total_adults: 2,
    total_children: 0,
    total_rooms: 1,
    holder_first_name: 'John',
    holder_last_name: 'Doe',
    holder_email: 'john@example.com',
    holder_phone: '+1234567890',
    property_name: hotels.data.hotels[0].name,
    base_price: hotels.data.hotels[0].pricing.base_price,
    total_price: hotels.data.hotels[0].pricing.final_price
  })
});
const booking = await bookingRes.json();
```

### 4. Process Payment

```javascript
const paymentRes = await fetch('/payments/guest/process', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    booking_reference: booking.data.booking_reference,
    payment_method: 'credit_card',
    card_number: '4111111111111111', // Test card
    card_holder_name: 'John Doe',
    card_expiry_month: '12',
    card_expiry_year: '2026',
    card_cvv: '123',
    billing_first_name: 'John',
    billing_last_name: 'Doe',
    billing_email: 'john@example.com',
    billing_address: '123 Main St',
    billing_city: 'New York',
    billing_country: 'USA',
    billing_postal_code: '10001'
  })
});
const payment = await paymentRes.json();

// Done! Booking confirmed
console.log(payment.data.transaction_id);
```

## 🎨 Admin Features

### Create Custom Markup Rules

```bash
# Login as admin
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'

# Create markup rule
curl -X POST http://localhost:8000/admin/pricing-markups \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Luxury Hotels 25%",
    "markup_type": "percentage",
    "markup_percentage": 25,
    "provider": "hotelbeds",
    "min_price": 300,
    "is_active": true
  }'
```

### Test Markup Calculator

```javascript
const calcRes = await fetch('/admin/pricing-markups/calculate', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: JSON.stringify({
    base_price: 350,
    provider: 'hotelbeds',
    property_type: 'hotel'
  })
});

const result = await calcRes.json();
console.log(result.data.breakdown);
// Shows: base_price, markup_percentage, markup_amount, final_price
```

## 📊 Pricing Examples

### Example 1: Percentage Markup

```json
{
  "name": "Standard Hotels 15%",
  "markup_type": "percentage",
  "markup_percentage": 15,
  "provider": "hotelbeds"
}
```
**Result**: $200 hotel → $230 final price

### Example 2: Fixed Amount

```json
{
  "name": "Flat $50 Fee",
  "markup_type": "fixed",
  "markup_fixed_amount": 50,
  "provider": "all"
}
```
**Result**: $200 hotel → $250 final price

### Example 3: Tiered Pricing

```json
{
  "name": "Smart Tiered Markup",
  "markup_type": "tiered",
  "tiered_pricing": [
    {"min": 0, "max": 100, "percentage": 25},
    {"min": 100, "max": 300, "percentage": 20},
    {"min": 300, "max": 999999, "percentage": 15}
  ]
}
```
**Results**:
- $80 hotel → $100 (25% markup)
- $200 hotel → $240 (20% markup)
- $400 hotel → $460 (15% markup)

### Example 4: Seasonal Pricing

```json
{
  "name": "Summer Peak 30%",
  "markup_type": "percentage",
  "markup_percentage": 30,
  "valid_from": "2025-06-01",
  "valid_to": "2025-08-31",
  "priority": 20
}
```

## 🔍 Common Use Cases

### Use Case 1: Different Markup by Provider

```php
// 15% for Hotelbeds
PricingMarkup::create([
    'name' => 'Hotelbeds Standard',
    'markup_type' => 'percentage',
    'markup_percentage' => 15,
    'provider' => 'hotelbeds'
]);

// 18% for OwnerRez
PricingMarkup::create([
    'name' => 'OwnerRez Standard',
    'markup_type' => 'percentage',
    'markup_percentage' => 18,
    'provider' => 'ownerrez'
]);
```

### Use Case 2: Premium Destination Pricing

```php
PricingMarkup::create([
    'name' => 'NYC Premium',
    'markup_type' => 'percentage',
    'markup_percentage' => 25,
    'destination_code' => 'NYC',
    'priority' => 10
]);
```

### Use Case 3: Property Type Pricing

```php
// Hotels: 15%
PricingMarkup::create([
    'name' => 'Hotels',
    'markup_type' => 'percentage',
    'markup_percentage' => 15,
    'property_type' => 'hotel'
]);

// Villas: 20%
PricingMarkup::create([
    'name' => 'Villas',
    'markup_type' => 'percentage',
    'markup_percentage' => 20,
    'property_type' => 'villa'
]);
```

## ✅ Verify Everything Works

### Checklist

- [ ] Migrations ran successfully
- [ ] Default markup created
- [ ] Public search returns results (with markup applied)
- [ ] Guest session can be created
- [ ] Guest booking can be created
- [ ] Property details endpoint works
- [ ] Admin can create markup rules
- [ ] Pricing calculator works

### Quick Test Script

```bash
# Create this file: test-api.sh
#!/bin/bash

echo "Testing public search..."
curl -X POST http://localhost:8000/public/search/hotels \
  -H "Content-Type: application/json" \
  -d '{"checkIn":"2025-03-01","checkOut":"2025-03-03","destination":"NYC","adults":2}'

echo "\n\nTesting guest session..."
curl -X POST http://localhost:8000/guest/session/create

echo "\n\nTesting destinations..."
curl http://localhost:8000/public/destinations

echo "\n\nAll tests complete!"
```

```bash
chmod +x test-api.sh
./test-api.sh
```

## 🎓 What's Next?

1. **Integrate Authorize.Net** - Update PaymentController with real API calls
2. **Add Email Notifications** - Welcome emails, booking confirmations
3. **Build Admin Dashboard** - UI for managing markups and bookings
4. **Set Up Frontend** - React/Vue app to consume these APIs
5. **Add More Markup Rules** - Seasonal, promotional, loyalty discounts

## 💡 Pro Tips

### Tip 1: Testing Markup Rules
Use the calculator endpoint to test before activating:
```bash
POST /admin/pricing-markups/calculate
```

### Tip 2: Debugging
Check `storage/logs/laravel.log` for detailed error messages

### Tip 3: Performance
Enable Redis in production for much faster searches

### Tip 4: Security
Always use HTTPS in production and enable rate limiting

---

**You're ready to go! 🚀**

Start with the public search endpoint and build from there. The entire booking flow works without authentication, making it perfect for a public-facing website like Booking.com.
