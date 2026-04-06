# PikPakGo — Frontend Developer API Guide
**Roman Urdu mein complete step-by-step guide**

---

## Base URL
```
http://yourdomain.com/api
```

> **Important:** Har authenticated request mein `Authorization` header zaroori hai:
> ```
> Authorization: Bearer {token}
> Content-Type: application/json
> Accept: application/json
> ```

---

# PART 1 — ADMIN PANEL

> Admin panel ke liye pehle login karo, token lo, phir saare admin APIs use karo.
> Har admin API ke liye `user_type = admin` hona zaroori hai warna 403 milega.

---

## STEP 1 — Admin Login

Admin pehli baar login karega. Token milega jo baaki saari calls mein use hoga.

```
POST /api/auth/login
```

**Body:**
```json
{
  "email": "admin@pikpakgo.com",
  "password": "yourpassword"
}
```

**Response (kamyabi par):**
```json
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@pikpakgo.com",
    "user_type": "admin"
  }
}
```

> **Yeh token localStorage mein save karo.** Aage har call mein yahi token header mein bhejoge.

---

## STEP 2 — Dashboard Stats (Home Page of Admin Panel)

Admin panel kholte hi yeh 4 calls karo aur cards/charts fill karo.

### 2.1 — Main Stats Cards
```
GET /api/admin/dashboard/stats
Authorization: Bearer {token}
```
**Response:** Total bookings, total revenue, active users, active properties — sab milega.

### 2.2 — Revenue Chart Data
```
GET /api/admin/dashboard/revenue
Authorization: Bearer {token}
```
**Response:** Monthly revenue breakdown milega — graph ke liye use karo.

### 2.3 — Bookings Chart
```
GET /api/admin/dashboard/bookings-chart
Authorization: Bearer {token}
```
**Response:** Har mahine kitni bookings huin — line/bar chart ke liye.

### 2.4 — Recent Bookings Table
```
GET /api/admin/dashboard/recent-bookings
Authorization: Bearer {token}
```
**Response:** Last 10 bookings — table mein dikhao.

### 2.5 — Top Properties
```
GET /api/admin/dashboard/top-properties
Authorization: Bearer {token}
```
**Response:** Sabse zyada book hone wali properties.

### 2.6 — User Growth Chart
```
GET /api/admin/dashboard/user-growth
Authorization: Bearer {token}
```
**Response:** Har mahine kitne naye users aaye.

---

## STEP 3 — Property Management

### 3.1 — Saari Properties List Karo
```
GET /api/admin/properties
Authorization: Bearer {token}
```

**Query Parameters (optional):**
```
?provider=ownerrez        → sirf ownerrez ki properties
?provider=direct          → sirf apni (fake/direct) properties
?search=Miami             → city ya naam se dhundho
?is_active=true           → sirf active properties
?page=2                   → pagination
```

**Response:** 20 properties per page paginated list milegi.

### 3.2 — Single Property Detail
```
GET /api/admin/properties/{id}
Authorization: Bearer {token}
```

### 3.3 — Property Active/Inactive Karo
```
PUT /api/admin/properties/{id}/status
Authorization: Bearer {token}
```
**Body:**
```json
{
  "is_active": false
}
```

### 3.4 — OwnerRez se Properties Sync Karo
Jab real OwnerRez credentials ho tab yeh button press karo — saari properties import ho jaengi.
```
POST /api/admin/properties/sync
Authorization: Bearer {token}
```
**Body (optional):**
```json
{
  "provider": "ownerrez"
}
```
**Response:**
```json
{
  "success": true,
  "message": "Synced 25 properties from ownerrez"
}
```

---

## STEP 4 — Booking Management

### 4.1 — Saari Bookings Dekho
```
GET /api/admin/bookings
Authorization: Bearer {token}
```

**Query Parameters:**
```
?status=confirmed          → confirmed bookings
?status=pending            → payment ka wait kar rahi bookings
?status=cancelled          → cancel hui bookings
?payment_status=paid       → paid bookings
?payment_status=pending    → unpaid bookings
?date_from=2025-01-01      → is date se
?date_to=2025-12-31        → is date tak
?page=1
```

### 4.2 — Single Booking Detail
```
GET /api/admin/bookings/{id}
Authorization: Bearer {token}
```
**Response mein:** Guest info, property info, payment info, provider payout status sab milega.

### 4.3 — Booking Status Change Karo
```
PUT /api/admin/bookings/{id}/status
Authorization: Bearer {token}
```
**Body:**
```json
{
  "status": "confirmed",
  "internal_notes": "Admin ne manually confirm kiya"
}
```
**Status options:** `pending`, `confirmed`, `cancelled`, `completed`, `no_show`, `rejected`

### 4.4 — Bookings CSV Export
```
GET /api/admin/bookings/export/csv
Authorization: Bearer {token}
```
> Yeh CSV file download karega. `window.location.href` ya `<a href>` se call karo.

---

## STEP 5 — Financial Reports (Platform ka Paisa)

Yeh section sabse important hai — aap kahan se kitna kama rahe ho yeh yahan dikhta hai.

### 5.1 — Financial Overview (Main Report)
```
GET /api/admin/financial/overview
Authorization: Bearer {token}
```

**Query Parameters:**
```
?from=2025-01-01
?to=2025-12-31
?group_by=month            → month | day | year
```

**Response mein yeh milega:**
```json
{
  "all_time": {
    "total_bookings": 150,
    "total_collected": 75000.00,    ← customers se liye paise
    "total_provider_cost": 60000.00, ← OwnerRez ko dene wale paise
    "total_platform_fee": 15000.00,  ← AAPKA PROFIT
    "total_net_profit": 15000.00,
    "avg_markup_pct": 20.00
  },
  "period_totals": { ... },
  "chart": [ ... ],                  ← graph data
  "payout_status": [ ... ],          ← OwnerRez ko send hua ya nahi
  "by_provider": [ ... ]             ← ownerrez vs direct breakdown
}
```

### 5.2 — Provider Payout List (OwnerRez ko bheje paise track karo)
```
GET /api/admin/financial/payouts
Authorization: Bearer {token}
```

**Query Parameters:**
```
?status=pending      → jinhe abhi OwnerRez ko nahi bheja
?status=sent         → jo OwnerRez ko bhej diye
?status=failed       → jo fail ho gaye (retry karo)
?status=not_required → direct properties (OwnerRez ki zaroorat nahi)
```

### 5.3 — Failed Payout Retry Karo (Single)
Agar kisi ek booking ka OwnerRez submission fail hua ho:
```
POST /api/admin/financial/payouts/{booking_id}/retry
Authorization: Bearer {token}
```

### 5.4 — Saare Failed Payouts Retry Karo
```
POST /api/admin/financial/payouts/retry-all-failed
Authorization: Bearer {token}
```

### 5.5 — Property-wise Profit
```
GET /api/admin/financial/profit-by-property
Authorization: Bearer {token}
?limit=10
```
**Response:** Konsi property se kitna profit hua — top 10 list milegi.

---

## STEP 6 — User Management

### 6.1 — Saare Users Dekho
```
GET /api/admin/users
Authorization: Bearer {token}
```
**Query Parameters:**
```
?search=john              → naam ya email se search
?user_type=customer       → customer | host | agency | admin
?is_active=true
?page=1
```

### 6.2 — Single User Detail
```
GET /api/admin/users/{id}
Authorization: Bearer {token}
```
**Response mein:** User ki info + uski booking history + total spent bhi milega.

### 6.3 — User ki Bookings Dekho
```
GET /api/admin/users/{id}/bookings
Authorization: Bearer {token}
```

### 6.4 — User Info Update Karo
```
PUT /api/admin/users/{id}
Authorization: Bearer {token}
```
**Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+1234567890"
}
```

### 6.5 — User Active/Inactive Karo
```
PUT /api/admin/users/{id}/status
Authorization: Bearer {token}
```
**Body:**
```json
{
  "is_active": false
}
```

### 6.6 — User ka Role Change Karo
```
PUT /api/admin/users/{id}/role
Authorization: Bearer {token}
```
**Body:**
```json
{
  "user_type": "host"
}
```
**Options:** `customer`, `host`, `agency`, `admin`

### 6.7 — User ka Password Reset Karo
```
POST /api/admin/users/{id}/reset-password
Authorization: Bearer {token}
```
**Body:**
```json
{
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

### 6.8 — User Delete Karo
```
DELETE /api/admin/users/{id}
Authorization: Bearer {token}
```

---

## STEP 7 — Pricing Markup (Apna Margin Set Karo)

> Yahan aap define karte ho ke OwnerRez ke price par kitna percent markup lagana hai.
> Markup automatically booking price mein add hota hai.

### 7.1 — Saare Markups Dekho
```
GET /api/admin/pricing-markups
Authorization: Bearer {token}
```

### 7.2 — Naya Markup Banao
```
POST /api/admin/pricing-markups
Authorization: Bearer {token}
```
**Body:**
```json
{
  "name": "Standard Markup",
  "type": "percentage",
  "value": 15,
  "is_default": true,
  "is_active": true,
  "description": "15% platform fee on all properties"
}
```
**type options:** `percentage` | `fixed`

### 7.3 — Markup Update Karo
```
PUT /api/admin/pricing-markups/{id}
Authorization: Bearer {token}
```

### 7.4 — Markup Enable/Disable Karo
```
PUT /api/admin/pricing-markups/{id}/toggle-status
Authorization: Bearer {token}
```

### 7.5 — Default Markup Set Karo
```
POST /api/admin/pricing-markups/set-default
Authorization: Bearer {token}
```
**Body:**
```json
{
  "id": 3
}
```

### 7.6 — Markup Calculate Karo (Test ke liye)
```
POST /api/admin/pricing-markups/calculate
Authorization: Bearer {token}
```
**Body:**
```json
{
  "base_price": 500,
  "provider": "ownerrez"
}
```
**Response:**
```json
{
  "base_price": 500,
  "markup_amount": 75,
  "markup_percentage": 15,
  "final_price": 575
}
```

### 7.7 — Markup Delete Karo
```
DELETE /api/admin/pricing-markups/{id}
Authorization: Bearer {token}
```

---

## STEP 8 — Reviews Moderation

### 8.1 — Saare Reviews Dekho
```
GET /api/admin/reviews
Authorization: Bearer {token}
?status=pending    → approve hone wale reviews
?status=approved
?status=rejected
```

### 8.2 — Review Approve Karo
```
PUT /api/admin/reviews/{id}/approve
Authorization: Bearer {token}
```

### 8.3 — Review Reject Karo
```
PUT /api/admin/reviews/{id}/reject
Authorization: Bearer {token}
```
**Body (optional):**
```json
{
  "reason": "Inappropriate content"
}
```

### 8.4 — Review par Reply Karo
```
PUT /api/admin/reviews/{id}/reply
Authorization: Bearer {token}
```
**Body:**
```json
{
  "reply": "Thank you for your feedback!"
}
```

### 8.5 — Review Delete Karo
```
DELETE /api/admin/reviews/{id}
Authorization: Bearer {token}
```

---

## STEP 9 — Settings

### 9.1 — Saari Settings Dekho
```
GET /api/admin/settings
Authorization: Bearer {token}
```
**Response:** Groups mein milega — general, booking, email, payment.

### 9.2 — Ek Setting Update Karo
```
PUT /api/admin/settings/{key}
Authorization: Bearer {token}
```
**Example** (`key = site_name`):
```json
{
  "value": "PikPakGo Premium"
}
```

### 9.3 — Bulk Settings Update Karo
```
PUT /api/admin/settings
Authorization: Bearer {token}
```
**Body:**
```json
{
  "settings": {
    "site_name": "PikPakGo",
    "default_currency": "USD",
    "booking_fee_percentage": "5"
  }
}
```

---

## STEP 10 — CMS (Content Management)

### 10.1 — Saare Pages Dekho
```
GET /api/admin/content
Authorization: Bearer {token}
```

### 10.2 — Naya Page Banao
```
POST /api/admin/content
Authorization: Bearer {token}
```
**Body:**
```json
{
  "title": "About Us",
  "slug": "about-us",
  "type": "page",
  "content": { "body": "<p>About us text...</p>" },
  "is_active": true
}
```

### 10.3 — Page Update Karo
```
PUT /api/admin/content/{id}
Authorization: Bearer {token}
```

### 10.4 — Page Delete Karo
```
DELETE /api/admin/content/{id}
Authorization: Bearer {token}
```

---

## STEP 11 — Contact Forms & Newsletter

### 11.1 — Contact Forms Dekho
```
GET /api/admin/contact-forms
Authorization: Bearer {token}
?status=new
?status=in_progress
?status=resolved
```

### 11.2 — Contact Form par Reply Karo
```
PUT /api/admin/contact-forms/{id}/reply
Authorization: Bearer {token}
```
**Body:**
```json
{
  "reply": "Apka masla hal ho gaya. Shukriya!"
}
```

### 11.3 — Newsletter Subscribers Dekho
```
GET /api/admin/newsletter-subscribers
Authorization: Bearer {token}
?status=active
?status=unsubscribed
```

---

## STEP 12 — Hosts & Agencies

### 12.1 — Saare Hosts Dekho
```
GET /api/admin/hosts
Authorization: Bearer {token}
```

### 12.2 — Host Verify/Reject Karo
```
PUT /api/admin/hosts/{id}/verify
Authorization: Bearer {token}
```
**Body:**
```json
{
  "action": "approve"
}
```
**action options:** `approve` | `reject`

### 12.3 — Saari Agencies Dekho
```
GET /api/admin/agencies
Authorization: Bearer {token}
```

---

## STEP 13 — Admin ka Token Refresh Karo

Token expire hone wala ho to:
```
POST /api/auth/refresh
Authorization: Bearer {old_token}
```
**Response mein naya token milega** — isko save karo aur purana discard karo.

---

## STEP 14 — Admin Logout
```
POST /api/auth/logout
Authorization: Bearer {token}
```

---
---
---

# PART 2 — WEBSITE (FRONTEND)

> Website ka flow customer ke liye hai. Kuch APIs bina login ke kaam karti hain (public), kuch ke liye login zaroori hai.

---

## STEP 1 — Site Load hote hi (App.js ya Layout mein)

Yeh calls site open hote hi karo — global state mein save karo.

### 1.1 — Site Info Lo (Currencies, Languages, Property Types)
```
GET /api/public/site-info
```
**Response:**
```json
{
  "currencies": ["USD", "EUR", "GBP", ...],
  "languages": [{"code": "en", "label": "English"}, ...],
  "property_types": ["house", "villa", "apartment", ...],
  "sort_options": [...],
  "guest_limits": {"min_adults": 1, "max_adults": 20}
}
```

### 1.2 — Public Settings Lo (Site Name, Logo, etc.)
```
GET /api/public/settings
```

### 1.3 — Header Content Lo
```
GET /api/public/content/header
```

### 1.4 — Footer Content Lo
```
GET /api/public/content/footer
```

---

## STEP 2 — Home Page

### 2.1 — Featured Properties (Hero Section)
```
GET /api/public/properties/featured
```
**Response:** 8 featured properties milegi — home page par dikhao.

### 2.2 — New Arrivals
```
GET /api/public/properties/new-arrivals
```

### 2.3 — Top Rated Properties
```
GET /api/public/properties/top-rated
```

### 2.4 — Popular Destinations
```
GET /api/public/search/popular-destinations
```

### 2.5 — Search Box mein Autocomplete (Jab user type kare)
```
GET /api/public/search/autocomplete?q=Mia
```
**Response:**
```json
[
  { "type": "city", "label": "Miami, Florida", "city": "Miami" },
  { "type": "property", "label": "Oceanfront Villa...", "id": 1 }
]
```

---

## STEP 3 — Search / Properties Listing Page

### 3.1 — Properties Search Karo (Main Search)
```
POST /api/public/search/properties
```
**Body:**
```json
{
  "destination": "Miami",
  "check_in": "2025-06-01",
  "check_out": "2025-06-07",
  "adults": 2,
  "children": 0,
  "rooms": 1
}
```

### 3.2 — Properties Filter/List Karo
```
GET /api/public/properties
```

**Query Parameters (filters):**
```
?city=Miami
?country=United States
?property_type=villa
?property_type[]=villa&property_type[]=cabin   → multiple types
?min_price=100
?max_price=500
?star_rating[]=4&star_rating[]=5
?min_rating=4.5
?bedrooms=3
?amenities[]=Pool&amenities[]=WiFi
?is_featured=true
?sort_by=price_asc         → newest | price_asc | price_desc | rating | popular
?per_page=12
?page=2
```

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [ ...properties array... ],
    "current_page": 1,
    "total": 45,
    "per_page": 12
  }
}
```

### 3.3 — Amenities List Lo (Filter Checkboxes ke liye)
```
GET /api/public/properties/amenities
```

### 3.4 — Property Types List Lo (Filter ke liye)
```
GET /api/public/properties/types
```

---

## STEP 4 — Property Detail Page

Jab user kisi property par click kare.

### 4.1 — Property Detail Lo
```
GET /api/public/properties/{id}
```
**Response mein:** Name, description, images, amenities, location, pricing, ratings sab milega.

### 4.2 — Similar Properties Lo (Sidebar ya bottom)
```
GET /api/public/properties/{id}/similar
```

### 4.3 — Property Reviews Lo
```
GET /api/public/properties/{id}/reviews
```

### 4.4 — Availability Calendar
```
GET /api/public/properties/{id}/calendar?month=2025-06
```
**Response:** Har din available hai ya booked — calendar render ke liye.

### 4.5 — Availability Check Karo (Jab dates select ho)
```
POST /api/public/properties/{id}/check-availability
```
**Body:**
```json
{
  "check_in_date": "2025-06-01",
  "check_out_date": "2025-06-07",
  "adults": 2,
  "children": 0
}
```

### 4.6 — Price Quote Lo (Dates confirm hone par)
```
POST /api/public/properties/{id}/get-pricing
```
**Body:**
```json
{
  "check_in_date": "2025-06-01",
  "check_out_date": "2025-06-07",
  "adults": 2,
  "children": 0
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "base_price": 500,
    "platform_fee": 75,
    "total_price": 575,
    "currency": "USD",
    "nights": 6,
    "breakdown": [ ... ]
  }
}
```

---

## STEP 5 — Booking Flow

> 2 tarah ke users book kar sakte hain:
> - **Guest** (bina account ke) → Guest flow use karo
> - **Logged In User** → Authenticated flow use karo

---

### GUEST BOOKING FLOW

#### 5.G.1 — Guest Session Banao (Site open hote hi)
```
POST /api/public/guest/session/create
```
**Body:**
```json
{
  "device_type": "web",
  "currency": "USD",
  "language": "en"
}
```
**Response:**
```json
{
  "session_id": "abc123xyz",
  "expires_at": "2025-07-01T..."
}
```
> **session_id ko localStorage mein save karo** — puri session mein use hoga.

#### 5.G.2 — Booking Banao (Checkout Page par submit par)
```
POST /api/bookings/guest/create
```
**Body:**
```json
{
  "guest_session_id": "abc123xyz",
  "property_code": "demo_101",
  "check_in_date": "2025-06-01",
  "check_out_date": "2025-06-07",
  "total_adults": 2,
  "total_children": 0,
  "total_rooms": 1,
  "holder_first_name": "Ahmed",
  "holder_last_name": "Ali",
  "holder_email": "ahmed@example.com",
  "holder_phone": "+923001234567",
  "property_name": "Oceanfront Villa",
  "property_city": "Miami",
  "property_country": "United States",
  "base_price": 500,
  "total_price": 575,
  "currency": "USD",
  "special_requests": "Late check-in please"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "booking_reference": "PKG-ABCD1234XY",
    "booking": { ... }
  }
}
```

#### 5.G.3 — Payment Karo
```
POST /api/payments/guest/process
```
**Body:**
```json
{
  "booking_reference": "PKG-ABCD1234XY",
  "payment_method": "credit_card",
  "card_number": "4111111111111111",
  "card_holder_name": "Ahmed Ali",
  "card_expiry_month": "12",
  "card_expiry_year": "2027",
  "card_cvv": "123",
  "billing_first_name": "Ahmed",
  "billing_last_name": "Ali",
  "billing_email": "ahmed@example.com",
  "billing_address": "123 Main St",
  "billing_city": "Karachi",
  "billing_state": "Sindh",
  "billing_country": "PK",
  "billing_postal_code": "75000"
}
```

**Kamyabi par response:**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "data": {
    "transaction_id": "TXN-XXXXXXXX",
    "booking_reference": "PKG-ABCD1234XY",
    "amount_charged": 575,
    "breakdown": {
      "base_price": 500,
      "platform_fee": 75,
      "total": 575
    }
  }
}
```

#### 5.G.4 — Booking Confirm Page par Booking Status Dekho
```
GET /api/bookings/guest/{bookingReference}/verify
```

#### 5.G.5 — Guest Booking Track Karo (Baad mein bhi)
```
GET /api/public/bookings/{bookingReference}/track?email=ahmed@example.com
```

#### 5.G.6 — Guest Booking Cancel Karo
```
POST /api/bookings/guest/{bookingReference}/cancel
```
**Body:**
```json
{
  "email": "ahmed@example.com",
  "reason": "Plans change ho gaye"
}
```

---

### LOGGED IN USER BOOKING FLOW

#### 5.U.1 — Pehle Login Karo
```
POST /api/auth/login
```
**Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

#### 5.U.2 — Booking Banao
```
POST /api/bookings
Authorization: Bearer {token}
```
**Body:**
```json
{
  "property_code": "demo_101",
  "check_in_date": "2025-06-01",
  "check_out_date": "2025-06-07",
  "total_adults": 2,
  "total_rooms": 1,
  "property_name": "Oceanfront Villa",
  "property_city": "Miami",
  "property_country": "United States",
  "base_price": 500,
  "provider": "direct",
  "special_requests": "High floor chahiye"
}
```

#### 5.U.3 — Payment Karo
```
POST /api/payments/process
Authorization: Bearer {token}
```
**Body:**
```json
{
  "booking_reference": "PKG-ABCD1234XY",
  "payment_method": "credit_card",
  "card_number": "4111111111111111",
  "card_holder_name": "Ahmed Ali",
  "card_expiry_month": "12",
  "card_expiry_year": "2027",
  "card_cvv": "123"
}
```

#### 5.U.4 — Apni Saari Bookings Dekho
```
GET /api/bookings
Authorization: Bearer {token}
?status=confirmed
?per_page=10
```

#### 5.U.5 — Single Booking Detail
```
GET /api/bookings/{bookingReference}
Authorization: Bearer {token}
```

#### 5.U.6 — Invoice PDF Download Karo
```
GET /api/bookings/{bookingReference}/invoice
Authorization: Bearer {token}
```
> Yeh PDF file return karta hai. `window.open(url)` ya `<a download>` se call karo.

#### 5.U.7 — Booking Cancel Karo
```
POST /api/bookings/{bookingReference}/cancel
Authorization: Bearer {token}
```
**Body:**
```json
{
  "reason": "Emergency aa gayi"
}
```

---

## STEP 6 — Payment Status Check Karo
```
GET /api/payments/{transactionId}/status
Authorization: Bearer {token}
```

**ya Guest ke liye:**
```
GET /api/payments/guest/{transactionId}/status
```

---

## STEP 7 — User Auth (Register, Login, Profile)

### 7.1 — Register
```
POST /api/auth/register
```
**Body:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Ali",
  "email": "ahmed@example.com",
  "password": "Password123",
  "password_confirmation": "Password123",
  "phone": "+923001234567"
}
```

### 7.2 — Email Verify Karo
Email mein link aayega — us link mein token hoga:
```
POST /api/auth/verify-email/{token}
```

### 7.3 — Verification Email Dobara Bhejo
```
POST /api/auth/resend-verification
```
**Body:**
```json
{
  "email": "ahmed@example.com"
}
```

### 7.4 — Forgot Password
```
POST /api/auth/forgot-password
```
**Body:**
```json
{
  "email": "ahmed@example.com"
}
```

### 7.5 — Password Reset
```
POST /api/auth/reset-password
```
**Body:**
```json
{
  "token": "reset_token_from_email",
  "email": "ahmed@example.com",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

### 7.6 — Logged In User ki Info Lo
```
GET /api/auth/me
Authorization: Bearer {token}
```

### 7.7 — Profile Update Karo
```
PUT /api/auth/profile
Authorization: Bearer {token}
```
**Body:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Khan",
  "phone": "+923009999999"
}
```

### 7.8 — Password Change Karo
```
POST /api/auth/change-password
Authorization: Bearer {token}
```
**Body:**
```json
{
  "current_password": "OldPass123",
  "password": "NewPass456",
  "password_confirmation": "NewPass456"
}
```

### 7.9 — Token Refresh Karo
```
POST /api/auth/refresh
Authorization: Bearer {old_token}
```

### 7.10 — Logout
```
POST /api/auth/logout
Authorization: Bearer {token}
```

---

## STEP 8 — Wishlist (Favourite Properties)

### 8.1 — Wishlist Dekho
```
GET /api/wishlist
Authorization: Bearer {token}
```

### 8.2 — Property Wishlist mein Add Karo
```
POST /api/wishlist
Authorization: Bearer {token}
```
**Body:**
```json
{
  "property_code": "demo_101"
}
```

### 8.3 — Check Karo ke Property Wishlist mein Hai Ya Nahi
```
GET /api/wishlist/check/{propertyCode}
Authorization: Bearer {token}
```
**Response:**
```json
{
  "in_wishlist": true
}
```

### 8.4 — Wishlist se Remove Karo
```
DELETE /api/wishlist/{propertyCode}
Authorization: Bearer {token}
```

---

## STEP 9 — Reviews

### 9.1 — Property ke Public Reviews Dekho (Bina Login)
```
GET /api/public/reviews/{propertyCode}
```

### 9.2 — Review Submit Karo (Login zaroori)
```
POST /api/reviews
Authorization: Bearer {token}
```
**Body:**
```json
{
  "property_code": "demo_101",
  "booking_reference": "PKG-ABCD1234XY",
  "rating": 5,
  "title": "Zabardast jagah thi!",
  "comment": "Pool aur view dono kamaal thay. Zaroor wapas aaenge.",
  "cleanliness_rating": 5,
  "location_rating": 5,
  "value_rating": 4
}
```

### 9.3 — Apne Reviews Dekho
```
GET /api/reviews/my
Authorization: Bearer {token}
```

### 9.4 — Review Update Karo
```
PUT /api/reviews/{id}
Authorization: Bearer {token}
```

### 9.5 — Review Delete Karo
```
DELETE /api/reviews/{id}
Authorization: Bearer {token}
```

---

## STEP 10 — Notifications (In-App)

### 10.1 — Saari Notifications Lo
```
GET /api/notifications
Authorization: Bearer {token}
?unread_only=true    → sirf unread
```

### 10.2 — Ek Notification Read Mark Karo
```
PUT /api/notifications/{id}/read
Authorization: Bearer {token}
```

### 10.3 — Saari Notifications Read Mark Karo
```
PUT /api/notifications/read-all
Authorization: Bearer {token}
```

### 10.4 — Ek Notification Delete Karo
```
DELETE /api/notifications/{id}
Authorization: Bearer {token}
```

### 10.5 — Saari Notifications Delete Karo
```
DELETE /api/notifications
Authorization: Bearer {token}
```

---

## STEP 11 — Public Pages (Contact, FAQ, Newsletter)

### 11.1 — Contact Form Submit Karo
```
POST /api/public/contact
```
**Body:**
```json
{
  "name": "Ahmed Ali",
  "email": "ahmed@example.com",
  "phone": "+923001234567",
  "subject": "Booking mein masla hai",
  "message": "Meri booking cancel ho gayi lekin refund nahi aya",
  "type": "booking_support",
  "booking_reference": "PKG-ABCD1234XY"
}
```
**type options:** `general`, `booking_support`, `property_issue`, `billing`, `other`

> **Rate Limit:** Ek IP se 5 message per hour allowed hain.

### 11.2 — Newsletter Subscribe Karo
```
POST /api/public/newsletter/subscribe
```
**Body:**
```json
{
  "email": "ahmed@example.com",
  "name": "Ahmed",
  "source": "footer"
}
```

### 11.3 — FAQ List Lo
```
GET /api/public/faqs
?category=booking       → booking | cancellation | payment | account | all
```

### 11.4 — CMS Page Content Lo
```
GET /api/public/content/pages/{slug}
```
**Example:**
```
GET /api/public/content/pages/about-us
GET /api/public/content/pages/privacy-policy
GET /api/public/content/pages/terms
```

---

## STEP 12 — Host/Agency Profile (Agar Host Portal banao)

### 12.1 — Host Profile Dekho
```
GET /api/profile/host
Authorization: Bearer {token}    (user_type = host)
```

### 12.2 — Host Profile Update Karo
```
PUT /api/profile/host
Authorization: Bearer {token}
```
**Body:**
```json
{
  "business_name": "Ali Rentals",
  "bio": "We offer premium vacation rentals",
  "website": "https://alirentals.com"
}
```

### 12.3 — Agency Profile Dekho
```
GET /api/profile/agency
Authorization: Bearer {token}    (user_type = agency)
```

### 12.4 — Agency Profile Update Karo
```
PUT /api/profile/agency
Authorization: Bearer {token}
```

---

## STEP 13 — Payment History
```
GET /api/payments/history
Authorization: Bearer {token}
```

---

# COMMON ERRORS aur Un ka Matlab

| Code | Matlab | Kya Karo |
|------|--------|----------|
| `401` | Token nahi hai ya expired hai | `/api/auth/refresh` call karo, ya dobara login |
| `403` | Permission nahi — admin area mein normal user | User ko admin banao ya route change karo |
| `404` | Cheez exist nahi | ID check karo |
| `422` | Validation error | `errors` field dekho — konsa field galat hai |
| `429` | Bohot zyada requests | Thoda ruko — rate limit hit hua |
| `500` | Server error | Backend developer ko batao |

---

# TOKEN MANAGEMENT — Important!

```javascript
// Login ke baad save karo
localStorage.setItem('token', response.data.token);

// Har API call mein header add karo
const headers = {
  'Authorization': `Bearer ${localStorage.getItem('token')}`,
  'Content-Type': 'application/json',
  'Accept': 'application/json'
};

// Token refresh (15 minute pehle karo expiry se)
async function refreshToken() {
  const res = await axios.post('/api/auth/refresh', {}, { headers });
  localStorage.setItem('token', res.data.token);
}
```

---

# COMPLETE BOOKING FLOW (Summary — Ek Nazar mein)

```
1. GET /api/public/site-info                    ← app start
2. POST /api/public/guest/session/create         ← guest session banao
3. GET /api/public/properties/featured           ← home page
4. GET /api/public/search/autocomplete?q=...     ← search type karte waqt
5. POST /api/public/search/properties            ← search results
6. GET /api/public/properties/{id}               ← property detail
7. POST /api/public/properties/{id}/check-availability
8. POST /api/public/properties/{id}/get-pricing  ← price quote
9. POST /api/bookings/guest/create               ← booking save karo
10. POST /api/payments/guest/process             ← payment lo
11. GET /api/bookings/guest/{ref}/verify         ← confirmation page
12. GET /api/public/bookings/{ref}/track?email=  ← baad mein track
```

---

*Document version: 1.0 | Backend: Laravel 11 | Auth: JWT Bearer Token*
