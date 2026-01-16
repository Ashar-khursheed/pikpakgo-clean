# PikPakGo - Enhanced Integration Guide

## 🎯 What's New in This Package

This enhanced version adds the following to your existing PikPakGo project:

### ✅ **5 New Core Features**

1. **Public Search API** - Search without authentication
2. **Dynamic Pricing Markup System** - Configurable profit margins
3. **Guest Booking Flow** - Book without account
4. **Session Tracking** - Convert guests to registered users
5. **Payment Processing** - Authorize.Net integration structure

### 📦 **New Files Added**

#### Database Migrations (5 new tables)
- `2025_01_16_000001_create_bookings_table.php`
- `2025_01_16_000002_create_pricing_markups_table.php`
- `2025_01_16_000003_create_property_listings_table.php`
- `2025_01_16_000004_create_guest_sessions_table.php`
- `2025_01_16_000005_create_payment_transactions_table.php`

#### New Controllers (WITH FULL SWAGGER ANNOTATIONS)
- `SearchController.php` - Public search
- `BookingController.php` - Guest + user bookings
- `PropertyController.php` - Property details
- `GuestController.php` - Session management
- `PaymentController.php` - Payment processing
- `Admin/PricingMarkupController.php` - Markup management

#### New Models
- `Booking.php`
- `PricingMarkup.php`
- `PropertyListing.php`
- `GuestSession.php`
- `PaymentTransaction.php`

#### New Services
- `PricingMarkupService.php`

---

## 🚀 Installation Steps

### Step 1: Backup Your Current Project

```bash
# Backup your routes
cp routes/api.php routes/api.php.backup

# Backup composer.json (if needed)
cp composer.json composer.json.backup
```

### Step 2: Extract and Merge Files

The package is already integrated with your existing structure. All files are ready to copy:

```bash
# Navigate to your project root
cd /path/to/your-pikpakgo-project

# Copy new migrations
cp database/migrations/2025_01_16_*.php ./database/migrations/

# Copy new controllers
cp app/Http/Controllers/SearchController.php ./app/Http/Controllers/
cp app/Http/Controllers/BookingController.php ./app/Http/Controllers/
cp app/Http/Controllers/PropertyController.php ./app/Http/Controllers/
cp app/Http/Controllers/GuestController.php ./app/Http/Controllers/
cp app/Http/Controllers/PaymentController.php ./app/Http/Controllers/
cp -r app/Http/Controllers/Admin ./app/Http/Controllers/

# Copy new models
cp app/Models/*.php ./app/Models/

# Copy new service
cp app/Services/PricingMarkupService.php ./app/Services/

# Replace routes (or manually merge)
cp routes/api.php ./routes/api.php
```

### Step 3: Update .env File

Add these new variables to your `.env`:

```env
# Guest Session Configuration
GUEST_SESSION_LIFETIME=43200  # 30 days in minutes
GUEST_SESSION_EXTENSION_DAYS=30

# Search Configuration
SEARCH_CACHE_DURATION=300  # 5 minutes
PROPERTY_SYNC_INTERVAL=12  # hours
POPULAR_DESTINATIONS_COUNT=20

# Pricing Configuration
DEFAULT_MARKUP_PERCENTAGE=15
MARKUP_CACHE_DURATION=1800  # 30 minutes

# Booking Configuration
BOOKING_REFERENCE_PREFIX=PKG
TRANSACTION_ID_PREFIX=TXN
FREE_CANCELLATION_HOURS=24

# Authorize.Net (if not already added)
AUTHORIZENET_LOGIN_ID=your_login_id
AUTHORIZENET_TRANSACTION_KEY=your_transaction_key
AUTHORIZENET_ENVIRONMENT=sandbox
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

### Step 5: Seed Default Pricing Markup

```bash
php artisan tinker
```

```php
\App\Models\PricingMarkup::create([
    'name' => 'Default 15% Markup',
    'description' => 'Standard markup for all properties',
    'markup_type' => 'percentage',
    'markup_percentage' => 15,
    'provider' => 'all',
    'is_active' => true,
    'is_default' => true,
    'priority' => 0
]);

exit;
```

### Step 6: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Step 7: Generate Swagger Documentation

```bash
php artisan l5-swagger:generate
```

---

## 📝 API Routes Overview

### Your Existing Routes (Preserved)
- ✅ `/auth/*` - Authentication (login, register, etc.)
- ✅ `/hotelbeds/*` - Hotelbeds API (now requires auth)
- ✅ `/ownerrez/*` - OwnerRez API (now requires auth)
- ✅ `/performance/*` - Performance monitoring

### New Public Routes (No Auth Required)
- 🆕 `POST /public/search/hotels` - Search hotels
- 🆕 `POST /public/search/properties` - Search vacation rentals
- 🆕 `GET /public/destinations` - Get destinations
- 🆕 `GET /public/popular-destinations` - Popular cities
- 🆕 `GET /public/properties/{id}` - Property details
- 🆕 `POST /public/properties/{id}/check-availability` - Check availability

### New Guest Booking Routes (No Auth)
- 🆕 `POST /guest/session/create` - Create guest session
- 🆕 `POST /bookings/guest/create` - Create guest booking
- 🆕 `GET /bookings/guest/{ref}` - Get guest booking
- 🆕 `POST /bookings/guest/{ref}/cancel` - Cancel booking

### New User Routes (Auth Required)
- 🆕 `GET /bookings` - Get user bookings
- 🆕 `POST /bookings` - Create user booking
- 🆕 `GET /bookings/{ref}` - Get booking details
- 🆕 `POST /bookings/{ref}/cancel` - Cancel booking

### New Admin Routes (Admin Role Required)
- 🆕 `GET /admin/pricing-markups` - List markup rules
- 🆕 `POST /admin/pricing-markups` - Create markup rule
- 🆕 `PUT /admin/pricing-markups/{id}` - Update markup
- 🆕 `DELETE /admin/pricing-markups/{id}` - Delete markup
- 🆕 `POST /admin/pricing-markups/calculate` - Test calculator

---

## 🔍 Swagger Documentation

All new controllers include complete Swagger/OpenAPI annotations compatible with your existing setup.

### View Swagger UI

After generating docs:

```bash
php artisan l5-swagger:generate
```

Visit: `http://your-domain/documentation`

### New Swagger Tags

- **Public Search** - Public endpoints
- **Guest Bookings** - Guest booking flow
- **User Bookings** - Authenticated bookings
- **Guest Sessions** - Session management
- **Payments** - Payment processing
- **Admin - Pricing** - Markup management

---

## 🎨 Key Differences from Original

### 1. Search is Now Public

**Before:**
```php
// Required authentication
Route::prefix('hotelbeds')->middleware('auth:api')->group(function () {
    Route::post('search', [HotelbedsController::class, 'searchHotels']);
});
```

**After:**
```php
// No authentication required
Route::prefix('public')->group(function () {
    Route::post('search/hotels', [SearchController::class, 'searchHotels']);
});
```

### 2. Automatic Pricing Markup

**Before:**
- API prices shown directly to users

**After:**
```php
// Base price: $200
// Markup: 15% ($30)
// Final price shown to user: $230
```

### 3. Guest Checkout Supported

**Before:**
- Users must register to book

**After:**
- Book as guest with email only
- Convert to account later (optional)

---

## 📊 Database Schema

### New Tables Created

1. **bookings** (40+ columns)
   - Supports both guest and user bookings
   - Tracks payment status
   - Handles cancellations and refunds

2. **pricing_markups** 
   - Configurable markup rules
   - Multiple types: percentage, fixed, tiered
   - Rule-based application

3. **property_listings**
   - Cached API data
   - Fast search capability
   - Sync management

4. **guest_sessions**
   - Track anonymous users
   - Conversion tracking
   - Activity monitoring

5. **payment_transactions**
   - Complete payment history
   - Multiple gateways support
   - Refund tracking

---

## 🧪 Testing

### Test Public Search

```bash
curl -X POST http://localhost:8000/public/search/hotels \
  -H "Content-Type: application/json" \
  -d '{
    "checkIn": "2025-03-01",
    "checkOut": "2025-03-03",
    "destination": "NYC",
    "adults": 2
  }'
```

### Test Swagger Documentation

```bash
# Generate docs
php artisan l5-swagger:generate

# Visit in browser
open http://localhost:8000/documentation
```

### Import Postman Collection

Use `PikPakGo-API.postman_collection.json` for complete API testing.

---

## 💡 Usage Examples

### Example 1: Create Percentage Markup

```php
PricingMarkup::create([
    'name' => 'Standard Hotels 15%',
    'markup_type' => 'percentage',
    'markup_percentage' => 15,
    'provider' => 'hotelbeds',
    'is_active' => true
]);
```

### Example 2: Create Tiered Pricing

```php
PricingMarkup::create([
    'name' => 'Smart Tiered Pricing',
    'markup_type' => 'tiered',
    'tiered_pricing' => [
        ['min' => 0, 'max' => 100, 'percentage' => 25],      // $0-100: 25%
        ['min' => 100, 'max' => 300, 'percentage' => 20],    // $100-300: 20%
        ['min' => 300, 'max' => 999999, 'percentage' => 15]  // $300+: 15%
    ],
    'provider' => 'all',
    'is_active' => true
]);
```

### Example 3: Seasonal Pricing

```php
PricingMarkup::create([
    'name' => 'Summer Peak Season',
    'markup_type' => 'percentage',
    'markup_percentage' => 30,
    'valid_from' => '2025-06-01',
    'valid_to' => '2025-08-31',
    'priority' => 20,  // Higher priority
    'is_active' => true
]);
```

---

## 🔧 Configuration

### Admin Middleware

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
    // ... existing
    'admin' => \App\Http\Middleware\AdminMiddleware::class,
];
```

---

## 📚 Additional Documentation

- **README.md** - Complete overview
- **QUICKSTART.md** - 5-minute setup guide
- **IMPLEMENTATION_GUIDE.md** - Detailed technical guide
- **Postman Collection** - API testing

---

## ✅ Verification Checklist

After installation, verify:

- [ ] Migrations ran successfully
- [ ] Default pricing markup created
- [ ] Public search works (no auth)
- [ ] Swagger documentation generated
- [ ] Guest session can be created
- [ ] Admin routes protected
- [ ] Existing authentication still works
- [ ] Your original Hotelbeds/OwnerRez services still work

---

## 🆘 Troubleshooting

### Swagger Not Generating

```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
php artisan l5-swagger:generate
```

### Routes Not Working

```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### Database Errors

```bash
# Fresh migration (WARNING: deletes data)
php artisan migrate:fresh

# Or run migrations individually
php artisan migrate --path=/database/migrations/2025_01_16_000001_create_bookings_table.php
```

---

## 🚀 Next Steps

1. **Test Public Search** - Verify no auth required
2. **Configure Markup Rules** - Set your pricing strategy
3. **Test Guest Booking Flow** - End-to-end guest checkout
4. **Integrate Authorize.Net** - Complete payment processing
5. **Build Frontend** - Connect to these APIs

---

**You now have a complete booking platform like Booking.com! 🎉**

The system supports:
- ✅ Public search without login
- ✅ Dynamic pricing with configurable markups
- ✅ Guest checkout (no account needed)
- ✅ Full Swagger documentation
- ✅ Payment processing ready
- ✅ Admin management tools
