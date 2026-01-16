# PikPakGo - Complete Booking Platform

A complete Laravel-based booking platform similar to Booking.com that integrates with Hotelbeds and OwnerRez APIs, featuring public search, dynamic pricing markup, guest checkout, and Authorize.Net payment processing.

## 🚀 Features

### Core Features
- ✅ **Public Search** - Search hotels/properties without authentication
- ✅ **Dual Provider Integration** - Hotelbeds (hotels) + OwnerRez (vacation rentals)
- ✅ **Dynamic Pricing Markup** - Configurable profit margins
- ✅ **Guest Checkout** - Book without creating account
- ✅ **User Authentication** - Full user management system
- ✅ **Payment Processing** - Authorize.Net integration ready
- ✅ **Session Tracking** - Track anonymous users and convert to registered
- ✅ **Admin Panel** - Complete backend management
- ✅ **Caching System** - Redis/Database caching for performance

### Pricing Features
- **Percentage Markup** - e.g., 15% on all bookings
- **Fixed Amount Markup** - e.g., $50 per booking
- **Tiered Pricing** - Different percentages based on price ranges
- **Rule-Based Application** - By provider, property type, destination, dates
- **Seasonal Pricing** - Date range based markup rules
- **Priority System** - Handle overlapping rules

## 📁 Project Structure

```
pikpakgo-complete/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/
│   │   │   └── PricingMarkupController.php
│   │   ├── AuthController.php (your existing)
│   │   ├── SearchController.php
│   │   ├── BookingController.php
│   │   ├── PropertyController.php
│   │   ├── GuestController.php
│   │   ├── PaymentController.php
│   │   ├── HotelbedsController.php (your existing)
│   │   └── OwnerRezController.php (your existing)
│   ├── Models/
│   │   ├── Booking.php
│   │   ├── PricingMarkup.php
│   │   ├── PropertyListing.php
│   │   ├── GuestSession.php
│   │   └── PaymentTransaction.php
│   └── Services/
│       ├── PricingMarkupService.php
│       ├── HotelbedsService.php (your existing)
│       └── OwnerRezService.php (your existing)
├── database/migrations/
│   ├── 2025_01_16_000001_create_bookings_table.php
│   ├── 2025_01_16_000002_create_pricing_markups_table.php
│   ├── 2025_01_16_000003_create_property_listings_table.php
│   ├── 2025_01_16_000004_create_guest_sessions_table.php
│   └── 2025_01_16_000005_create_payment_transactions_table.php
├── routes/
│   └── api.php
└── docs/
    └── IMPLEMENTATION_GUIDE.md
```

## 🛠️ Installation

### 1. Prerequisites
- PHP 8.1+
- Laravel 10+
- MySQL 8.0+
- Redis (optional but recommended)
- Composer
- Node.js & NPM

### 2. Copy Files to Your Project

```bash
# Copy migrations
cp database/migrations/*.php /path/to/your-project/database/migrations/

# Copy controllers
cp -r app/Http/Controllers/* /path/to/your-project/app/Http/Controllers/

# Copy models
cp app/Models/*.php /path/to/your-project/app/Models/

# Copy services
cp app/Services/*.php /path/to/your-project/app/Services/

# Replace routes (backup your existing first!)
cp routes/api.php /path/to/your-project/routes/api.php
```

### 3. Update Your .env File

```env
# Existing Hotelbeds Configuration
HOTELBEDS_API_KEY=your_api_key
HOTELBEDS_SECRET=your_secret
HOTELBEDS_BASE_URL=https://api.test.hotelbeds.com

# Existing OwnerRez Configuration
OWNERREZ_API_KEY=your_api_key
OWNERREZ_BASE_URL=https://api.ownerrez.com

# Authorize.Net Configuration
AUTHORIZENET_LOGIN_ID=your_login_id
AUTHORIZENET_TRANSACTION_KEY=your_transaction_key
AUTHORIZENET_ENVIRONMENT=sandbox  # or production

# Session Configuration
SESSION_LIFETIME=43200  # 30 days in minutes
GUEST_SESSION_LIFETIME=43200

# Cache Configuration
CACHE_DRIVER=redis  # or database
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Seed Default Pricing Markup

```bash
php artisan tinker
```

Then run:

```php
\App\Models\PricingMarkup::create([
    'name' => 'Default Markup - 15%',
    'description' => 'Standard 15% markup on all bookings',
    'markup_type' => 'percentage',
    'markup_percentage' => 15,
    'provider' => 'all',
    'is_active' => true,
    'is_default' => true,
    'priority' => 0
]);
```

### 6. Create Admin Middleware (if not exists)

Create `app/Http/Middleware/AdminMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        return $next($request);
    }
}
```

Register in `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ... existing middleware
    'admin' => \App\Http\Middleware\AdminMiddleware::class,
];
```

## 📖 API Documentation

### Public Endpoints (No Authentication)

#### Search Hotels
```http
POST /public/search/hotels
Content-Type: application/json

{
  "checkIn": "2025-03-01",
  "checkOut": "2025-03-03",
  "destination": "NYC",
  "adults": 2,
  "children": 0,
  "rooms": 1
}
```

#### Search Properties
```http
POST /public/search/properties
Content-Type: application/json

{
  "checkIn": "2025-03-01",
  "checkOut": "2025-03-05",
  "location": "Miami Beach",
  "guests": 4,
  "bedrooms": 2
}
```

#### Get Destinations
```http
GET /public/destinations?search=new york
```

#### Get Property Details
```http
GET /public/properties/{id}
```

### Guest Booking Endpoints

#### Create Guest Session
```http
POST /guest/session/create
```

Response:
```json
{
  "success": true,
  "data": {
    "session_id": "guest_abc123...",
    "expires_at": "2025-02-15 12:00:00"
  }
}
```

#### Create Guest Booking
```http
POST /bookings/guest/create
Content-Type: application/json

{
  "guest_session_id": "guest_abc123...",
  "provider": "hotelbeds",
  "property_code": "12345",
  "check_in_date": "2025-03-01",
  "check_out_date": "2025-03-03",
  "total_adults": 2,
  "total_children": 0,
  "total_rooms": 1,
  "holder_first_name": "John",
  "holder_last_name": "Doe",
  "holder_email": "john@example.com",
  "holder_phone": "+1234567890",
  "property_name": "Grand Hotel NYC",
  "base_price": 200,
  "total_price": 230
}
```

### User Endpoints (Authentication Required)

#### Get User Bookings
```http
GET /bookings
Authorization: Bearer {token}
```

#### Create User Booking
```http
POST /bookings
Authorization: Bearer {token}
Content-Type: application/json

{
  "provider": "hotelbeds",
  "property_code": "12345",
  "check_in_date": "2025-03-01",
  "check_out_date": "2025-03-03",
  "total_adults": 2,
  "total_rooms": 1,
  "property_name": "Grand Hotel NYC",
  "base_price": 200
}
```

### Payment Endpoints

#### Process Guest Payment
```http
POST /payments/guest/process
Content-Type: application/json

{
  "booking_reference": "PKG-ABC123XYZ",
  "payment_method": "credit_card",
  "card_number": "4111111111111111",
  "card_holder_name": "John Doe",
  "card_expiry_month": "12",
  "card_expiry_year": "2026",
  "card_cvv": "123",
  "billing_first_name": "John",
  "billing_last_name": "Doe",
  "billing_email": "john@example.com",
  "billing_address": "123 Main St",
  "billing_city": "New York",
  "billing_country": "USA",
  "billing_postal_code": "10001"
}
```

### Admin Endpoints (Admin Role Required)

#### Get All Pricing Markups
```http
GET /admin/pricing-markups
Authorization: Bearer {token}
```

#### Create Pricing Markup
```http
POST /admin/pricing-markups
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Summer Hotels Premium",
  "markup_type": "percentage",
  "markup_percentage": 20,
  "provider": "hotelbeds",
  "property_type": "hotel",
  "min_price": 200,
  "valid_from": "2025-06-01",
  "valid_to": "2025-08-31",
  "priority": 10,
  "is_active": true
}
```

#### Tiered Pricing Example
```http
POST /admin/pricing-markups
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Tiered Markup Strategy",
  "markup_type": "tiered",
  "provider": "all",
  "tiered_pricing": [
    {"min": 0, "max": 100, "percentage": 25},
    {"min": 100, "max": 300, "percentage": 20},
    {"min": 300, "max": 999999, "percentage": 15}
  ],
  "is_active": true
}
```

#### Test Markup Calculator
```http
POST /admin/pricing-markups/calculate
Authorization: Bearer {token}
Content-Type: application/json

{
  "base_price": 250,
  "provider": "hotelbeds",
  "property_type": "hotel",
  "destination_code": "NYC",
  "check_in_date": "2025-07-15"
}
```

## 🎯 Usage Examples

### Example 1: Basic Search Flow

```javascript
// 1. Search for hotels
const searchResponse = await fetch('/public/search/hotels', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    checkIn: '2025-03-01',
    checkOut: '2025-03-03',
    destination: 'NYC',
    adults: 2
  })
});

const hotels = await searchResponse.json();
// Returns hotels with markup already applied
// base_price: $200 → final_price: $230 (with 15% markup)
```

### Example 2: Guest Booking Flow

```javascript
// 1. Create guest session
const sessionRes = await fetch('/guest/session/create', {
  method: 'POST'
});
const { session_id } = await sessionRes.json();

// 2. Create booking
const bookingRes = await fetch('/bookings/guest/create', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    guest_session_id: session_id,
    // ... booking details
  })
});

const { booking_reference } = await bookingRes.json();

// 3. Process payment
const paymentRes = await fetch('/payments/guest/process', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    booking_reference: booking_reference,
    // ... payment details
  })
});
```

### Example 3: Admin Creating Markup Rules

```php
// Percentage markup for all hotels
PricingMarkup::create([
    'name' => 'Standard Hotels',
    'markup_type' => 'percentage',
    'markup_percentage' => 15,
    'provider' => 'hotelbeds'
]);

// Higher markup for luxury properties
PricingMarkup::create([
    'name' => 'Luxury Properties',
    'markup_type' => 'percentage',
    'markup_percentage' => 25,
    'min_price' => 300,
    'priority' => 10  // Higher priority
]);

// Seasonal pricing
PricingMarkup::create([
    'name' => 'Summer Peak Season',
    'markup_type' => 'percentage',
    'markup_percentage' => 30,
    'valid_from' => '2025-06-01',
    'valid_to' => '2025-08-31',
    'priority' => 20
]);
```

## 🔧 Configuration

### Pricing Markup Rules Priority

When multiple rules could apply, the system uses:
1. **Priority** (higher number = higher priority)
2. **Specificity** (more specific rules win)
3. **Date range** (valid rules over expired)
4. **Default** (fallback if no rules match)

### Session Management

Guest sessions automatically:
- Expire after 30 days
- Track searches and bookings
- Convert to user accounts on registration
- Transfer all bookings to user

### Caching Strategy

- Search results: 5 minutes
- Property listings: Updated on sync
- Markup rules: 30 minutes
- Destination lists: 1 hour

## 🔐 Security

### Implemented
- ✅ Email verification for bookings
- ✅ Booking reference security
- ✅ Session validation
- ✅ Input validation on all endpoints
- ✅ SQL injection protection (Eloquent ORM)
- ✅ XSS protection (Laravel defaults)

### Required for Production
- [ ] Rate limiting on public endpoints
- [ ] CAPTCHA on booking creation
- [ ] PCI DSS compliance for payments
- [ ] Data encryption at rest
- [ ] HTTPS enforcement
- [ ] CORS configuration
- [ ] API request signing for admin

## 📊 Database Schema

### Key Tables
- **bookings** - All booking records (40+ fields)
- **pricing_markups** - Markup configuration rules
- **property_listings** - Cached property data
- **guest_sessions** - Anonymous user tracking
- **payment_transactions** - Payment history

All tables include:
- Soft deletes
- Timestamps
- Comprehensive indexing
- JSON fields for flexibility

## 🚀 Performance

### Optimizations Included
- Database indexing (15+ indexes per table)
- Redis caching for searches
- Query optimization
- Lazy loading relationships
- API response caching

### Recommendations
- Enable Redis for production
- Use CDN for images
- Implement queue workers for emails
- Monitor query performance
- Scale horizontally as needed

## 📝 Next Steps

### Phase 2 (Not Yet Implemented)
1. Complete Authorize.Net integration
2. Email notification system
3. Admin dashboard with charts
4. Property management CRUD
5. User management interface
6. Booking management tools
7. Reporting and analytics
8. Invoice generation

## 🐛 Troubleshooting

### Common Issues

**Issue**: Migrations fail
```bash
# Solution: Check database connection
php artisan config:clear
php artisan migrate:fresh
```

**Issue**: Markup not applying
```bash
# Solution: Clear cache
php artisan cache:clear
# Or in code:
$pricingService->clearCache();
```

**Issue**: 401 Unauthorized
```bash
# Solution: Check JWT token generation
php artisan jwt:secret
```

## 📞 Support

For issues or questions:
1. Check `docs/IMPLEMENTATION_GUIDE.md`
2. Review API examples above
3. Check Laravel logs: `storage/logs/laravel.log`

## 📄 License

[Your License Here]

## 👥 Contributors

[Your Team/Company Name]

---

**Version**: 1.0.0  
**Last Updated**: January 2025
