# URGENT FIX - Swagger Double /api Issue

## Problem
Your Swagger documentation is showing URLs like:
```
https://pickpackgo.in-sourceit.com/api/public/search/hotels
                                    ↑   ↑
                                    Double /api
```

This causes 404 errors because the correct URL should be:
```
https://pickpackgo.in-sourceit.com/public/search/hotels
```

## Solution - Update Controller.php

Replace your `app/Http/Controllers/Controller.php` with this:

```php
<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="PikPakGo API",
 *     version="1.0.0",
 *     description="PikPakGo Travel Marketplace API - Hotels, Vacation Rentals & More",
 *     @OA\Contact(
 *         email="reservations@pikpakgo.com",
 *         name="PikPakGo Support"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://pickpackgo.in-sourceit.com/api",
 *     description="Production Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     type="http",
 *     description="Login with email and password to get the authentication token",
 *     name="Token based authentication",
 *     in="header",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="bearerAuth",
 * )
 */
abstract class Controller
{
    //
}
```

## Changed Lines

**BEFORE:**
```php
@OA\Server(
    url="http://localhost:8000",           // ❌ Missing /api
    description="Local Development Server"
)

@OA\Server(
    url="https://pickpackgo.in-sourceit.com/api",  // ✅ Already has /api
    description="Production Server"
)
```

**AFTER:**
```php
@OA\Server(
    url="http://localhost:8000/api",       // ✅ Added /api
    description="Local Development Server"
)

@OA\Server(
    url="https://pickpackgo.in-sourceit.com/api",  // ✅ Still has /api
    description="Production Server"
)
```

## Steps to Fix

### Step 1: Update Controller.php
```bash
# Copy the corrected file from the package
cp app/Http/Controllers/Controller.php /path/to/your/project/app/Http/Controllers/Controller.php
```

### Step 2: Regenerate Swagger Documentation
```bash
php artisan l5-swagger:generate
```

### Step 3: Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Step 4: Test
Visit: `https://pickpackgo.in-sourceit.com/documentation`

The URLs should now be correct:
```
✅ https://pickpackgo.in-sourceit.com/public/search/hotels
✅ https://pickpackgo.in-sourceit.com/bookings/guest/create
✅ https://pickpackgo.in-sourceit.com/auth/login
```

## Alternative: Check l5-swagger.php Config

If the issue persists, also check your `config/l5-swagger.php`:

```php
'defaults' => [
    'routes' => [
        'api' => 'api/documentation',  // Make sure this doesn't duplicate
    ],
    // ...
],
```

## Verification

After the fix, test with curl:

```bash
# Test public search (should work without auth)
curl -X POST https://pickpackgo.in-sourceit.com/public/search/hotels \
  -H "Content-Type: application/json" \
  -d '{
    "checkIn": "2025-03-01",
    "checkOut": "2025-03-03",
    "destination": "NYC",
    "adults": 2
  }'
```

You should get a 200 response with hotel data, NOT a 404!

## Quick Test Checklist

After fixing:

- [ ] Swagger UI loads at `/documentation`
- [ ] Server dropdown shows correct URLs (no double /api)
- [ ] Test search endpoint returns 200, not 404
- [ ] All routes work in Swagger "Try it out"

---

**This fix is included in the updated package!**
