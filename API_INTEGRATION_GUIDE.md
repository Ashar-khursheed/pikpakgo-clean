# PikPakGo API - Hotelbeds & OwnerRez Integration

This document describes the newly integrated APIs for hotel bookings (Hotelbeds) and vacation rental properties (OwnerRez).

## Table of Contents
- [Overview](#overview)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Hotelbeds API](#hotelbeds-api)
- [OwnerRez API](#ownerrez-api)
- [Error Handling](#error-handling)
- [Caching Strategy](#caching-strategy)

## Overview

PikPakGo now integrates with two major travel service providers:

1. **Hotelbeds** - Global hotel booking platform
2. **OwnerRez** - Vacation rental property management

Both APIs are protected with JWT authentication and include comprehensive caching for optimal performance.

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# Hotelbeds Configuration
HOTELBEDS_API_KEY=6R4b3Ujs
HOTELBEDS_SECRET=48j86rA62LMNTuG4
HOTELBEDS_BASE_URL=https://api.test.hotelbeds.com

# OwnerRez Configuration
OWNERREZ_USERNAME=HaXmlSandboxMoR
OWNERREZ_PASSWORD=0beefaaa-963c-407c-bd64-bdeadb949417
OWNERREZ_BASE_URL=https://faststage.ownerrez.com
OWNERREZ_ENVIRONMENT=sandbox

# Authorize.net Payment Gateway (Optional)
AUTHORIZE_NET_API_LOGIN_ID=
AUTHORIZE_NET_TRANSACTION_KEY=
AUTHORIZE_NET_BASE_URL=https://apitest.authorize.net/xml/v1/request.api
AUTHORIZE_NET_ENVIRONMENT=sandbox
```

### Production vs Sandbox

The credentials provided are for **sandbox/testing** environments. Before going to production:

1. Update `HOTELBEDS_BASE_URL` to production URL
2. Update `OWNERREZ_BASE_URL` to production URL
3. Update `OWNERREZ_ENVIRONMENT` to `production`
4. Obtain production API credentials from providers

## Authentication

All API endpoints require JWT authentication. Include the bearer token in the Authorization header:

```bash
Authorization: Bearer YOUR_JWT_TOKEN
```

To obtain a token, use the login endpoint:

```bash
POST /auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

## Hotelbeds API

Base URL: `/hotelbeds`

### 1. Search Hotels

Search for available hotels based on destination and dates.

**Endpoint:** `POST /hotelbeds/search`

**Request:**
```json
{
  "checkIn": "2024-12-25",
  "checkOut": "2024-12-27",
  "destinationCode": "NYC",
  "occupancies": [
    {
      "rooms": 1,
      "adults": 2,
      "children": 0
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "hotels": [...],
    "total": 150
  }
}
```

**Caching:** Results cached for 1 hour

---

### 2. Get Hotel Details

Retrieve detailed information about a specific hotel.

**Endpoint:** `GET /hotelbeds/hotels/{hotelCode}`

**Example:** `GET /hotelbeds/hotels/12345`

**Response:**
```json
{
  "success": true,
  "data": {
    "code": "12345",
    "name": "Grand Hotel",
    "description": "...",
    "facilities": [...],
    "images": [...],
    "address": {...}
  }
}
```

**Caching:** Results cached for 24 hours

---

### 3. Check Availability

Verify room availability and get updated rates.

**Endpoint:** `POST /hotelbeds/check-availability`

**Request:**
```json
{
  "rooms": [
    {
      "rateKey": "20241225|20241227|W|1|12345|DBL.ST|BAR|..."
    }
  ],
  "upselling": false
}
```

---

### 4. Create Booking

Create a new hotel reservation.

**Endpoint:** `POST /hotelbeds/bookings`

**Request:**
```json
{
  "holder": {
    "name": "John",
    "surname": "Doe"
  },
  "rooms": [
    {
      "rateKey": "...",
      "paxes": [
        {
          "roomId": 1,
          "type": "AD",
          "name": "John",
          "surname": "Doe"
        }
      ]
    }
  ],
  "clientReference": "CLIENT-REF-12345"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "reference": "1-12345",
    "status": "CONFIRMED",
    "totalNet": 250.00
  }
}
```

---

### 5. Get Booking Details

Retrieve information about an existing booking.

**Endpoint:** `GET /hotelbeds/bookings/{bookingReference}`

**Example:** `GET /hotelbeds/bookings/1-12345`

---

### 6. Cancel Booking

Cancel an existing reservation.

**Endpoint:** `DELETE /hotelbeds/bookings/{bookingReference}`

**Example:** `DELETE /hotelbeds/bookings/1-12345`

**Response:**
```json
{
  "success": true,
  "data": {
    "reference": "1-12345",
    "status": "CANCELLED",
    "cancellationAmount": 50.00
  }
}
```

---

## OwnerRez API

Base URL: `/ownerrez`

### 1. Search Properties

Search for vacation rental properties.

**Endpoint:** `GET /ownerrez/properties`

**Query Parameters:**
- `location` - Location or city name (optional)
- `checkin` - Check-in date YYYY-MM-DD (optional)
- `checkout` - Check-out date YYYY-MM-DD (optional)
- `guests` - Number of guests (optional)
- `bedrooms` - Minimum bedrooms (optional)
- `bathrooms` - Minimum bathrooms (optional)
- `minPrice` - Minimum price per night (optional)
- `maxPrice` - Maximum price per night (optional)

**Example:** `GET /ownerrez/properties?location=Miami+Beach&guests=4&bedrooms=2`

**Response:**
```json
{
  "success": true,
  "data": {
    "properties": [...],
    "total": 45
  }
}
```

**Caching:** Results cached for 1 hour

---

### 2. Get Property Details

Get detailed information about a specific property.

**Endpoint:** `GET /ownerrez/properties/{propertyId}`

**Example:** `GET /ownerrez/properties/PROP-12345`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "PROP-12345",
    "name": "Beachfront Villa",
    "description": "...",
    "bedrooms": 3,
    "bathrooms": 2,
    "amenities": [...],
    "images": [...]
  }
}
```

**Caching:** Results cached for 24 hours

---

### 3. Check Availability

Check if a property is available for specific dates.

**Endpoint:** `POST /ownerrez/properties/{propertyId}/availability`

**Request:**
```json
{
  "checkin": "2024-12-25",
  "checkout": "2024-12-27",
  "guests": 4
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "available": true,
    "minStay": 2,
    "maxStay": 14
  }
}
```

---

### 4. Get Pricing

Get pricing information for a property.

**Endpoint:** `POST /ownerrez/properties/{propertyId}/pricing`

**Request:**
```json
{
  "checkin": "2024-12-25",
  "checkout": "2024-12-27",
  "guests": 4
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "basePrice": 300.00,
    "cleaningFee": 75.00,
    "taxes": 25.00,
    "total": 400.00,
    "currency": "USD"
  }
}
```

---

### 5. Create Booking

Create a new property reservation.

**Endpoint:** `POST /ownerrez/bookings`

**Request:**
```json
{
  "propertyId": "PROP-12345",
  "checkin": "2024-12-25",
  "checkout": "2024-12-27",
  "guest": {
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890"
  },
  "guests": 4,
  "specialRequests": "Late check-in"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "bookingId": "BOOK-12345",
    "status": "CONFIRMED",
    "confirmationCode": "ABC123"
  }
}
```

---

### 6. Get Booking Details

Retrieve information about a booking.

**Endpoint:** `GET /ownerrez/bookings/{bookingId}`

**Example:** `GET /ownerrez/bookings/BOOK-12345`

---

### 7. Update Booking

Modify an existing booking.

**Endpoint:** `PUT /ownerrez/bookings/{bookingId}`

**Request:**
```json
{
  "guests": 5,
  "specialRequests": "Early check-in"
}
```

---

### 8. Cancel Booking

Cancel a property reservation.

**Endpoint:** `DELETE /ownerrez/bookings/{bookingId}`

**Example:** `DELETE /ownerrez/bookings/BOOK-12345`

---

### 9. Get Property Reviews

Retrieve reviews for a property.

**Endpoint:** `GET /ownerrez/properties/{propertyId}/reviews`

**Example:** `GET /ownerrez/properties/PROP-12345/reviews`

**Response:**
```json
{
  "success": true,
  "data": {
    "reviews": [
      {
        "rating": 5,
        "comment": "Amazing property!",
        "author": "Jane D.",
        "date": "2024-11-15"
      }
    ],
    "averageRating": 4.8,
    "totalReviews": 42
  }
}
```

**Caching:** Results cached for 1 hour

---

## Error Handling

All endpoints return consistent error responses:

### Validation Error (400)
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "checkIn": ["The check in field is required."]
  }
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "An error occurred while processing your request",
  "error": "Detailed error message"
}
```

## Caching Strategy

To optimize performance and reduce API calls:

1. **Search Results**: Cached for 1 hour
2. **Property/Hotel Details**: Cached for 24 hours
3. **Reviews**: Cached for 1 hour
4. **Availability & Pricing**: Not cached (real-time data)

Cache keys are generated using MD5 hash of request parameters.

To clear cache manually:
```bash
POST /performance/clear-cache
Authorization: Bearer YOUR_JWT_TOKEN
```

## Swagger Documentation

Access interactive API documentation at:
```
http://your-domain/documentation
```

Generate/update Swagger docs:
```bash
php artisan l5-swagger:generate
```

## Testing

Use the provided Postman collection: `PikPakGo-API.postman_collection.json`

Or test with cURL:

```bash
# Login
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# Search Hotels
curl -X POST http://localhost:8000/hotelbeds/search \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "checkIn": "2024-12-25",
    "checkOut": "2024-12-27",
    "destinationCode": "NYC"
  }'

# Search Properties
curl -X GET "http://localhost:8000/ownerrez/properties?location=Miami&guests=4" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Support

For questions or issues:
- Email: reservations@pikpakgo.com
- Check logs: `storage/logs/laravel.log`
- Monitor performance: `GET /performance/cache-stats`

## Next Steps

1. **Payment Integration**: Integrate Authorize.net for processing payments
2. **Email Notifications**: Send booking confirmations via email
3. **Admin Panel**: Create admin interface for managing bookings
4. **Mobile App**: Build native mobile apps consuming these APIs
5. **Analytics**: Add tracking and reporting for bookings
