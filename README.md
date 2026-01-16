# PikPakGo API - Authentication Module

Complete authentication and authorization system for PikPakGo unified travel marketplace.

## Features Implemented

### Authentication Module ✅
- User Registration (Customer, Host, Agency)
- User Login with JWT
- Email Verification
- Password Reset/Forgot Password
- Change Password
- Token Refresh
- Logout
- Get Current User
- Role-based Access Control
- Email Verification Status Check

### User Types Supported
1. **Customer** - Regular travelers
2. **Host** - Property owners (with host profile)
3. **Agency** - B2B travel agency partners (with agency profile)
4. **Admin** - System administrators

## Installation & Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

### 3. Database Setup

Update your `.env` file with database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pikpakgo
DB_USERNAME=root
DB_PASSWORD=your_password
```

Run migrations:

```bash
php artisan migrate
```

### 4. Generate Swagger Documentation

```bash
php artisan l5-swagger:generate
```

### 5. Start Development Server

```bash
php artisan serve
```

API will be available at: `http://localhost:8000/api`

## Access Swagger Documentation

Once the server is running, access the Swagger UI at:

```
http://localhost:8000/documentation
```

## API Endpoints

### Public Endpoints (No Authentication Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | Login user |
| POST | `/auth/forgot-password` | Request password reset |
| POST | `/auth/reset-password` | Reset password with token |
| POST | `/auth/verify-email/{token}` | Verify email address |
| POST | `/auth/resend-verification` | Resend verification email |

### Protected Endpoints (Authentication Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/logout` | Logout user |
| POST | `/auth/refresh` | Refresh JWT token |
| GET | `/auth/me` | Get current user |
| POST | `/auth/change-password` | Change password |

## Request Examples

### 1. Register Customer

```json
POST /auth/register

{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "+1234567890",
    "user_type": "customer",
    "country": "USA",
    "preferred_currency": "USD",
    "preferred_language": "en"
}
```

### 2. Register Host

```json
POST /auth/register

{
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "user_type": "host",
    "host_profile": {
        "business_name": "Luxury Rentals LLC",
        "business_registration_number": "REG123456",
        "bio": "Professional property management with 10+ years experience"
    }
}
```

### 3. Register Agency

```json
POST /auth/register

{
    "first_name": "Mike",
    "last_name": "Johnson",
    "email": "mike@travelagency.com",
    "password": "password123",
    "password_confirmation": "password123",
    "user_type": "agency",
    "agency_profile": {
        "agency_name": "Global Travel Agency",
        "agency_registration_number": "AGY123456",
        "tax_id": "TAX123456",
        "website": "https://globaltravelagency.com"
    }
}
```

### 4. Login

```json
POST /auth/login

{
    "email": "john@example.com",
    "password": "password123",
    "remember_me": false
}
```

### 5. Get Current User (Protected)

```bash
GET /auth/me
Headers: Authorization: Bearer {your-jwt-token}
```

## Response Format

All API responses follow this structure:

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        // Response data here
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "errors": {
        // Validation errors or error details
    }
}
```

## JWT Token Usage

After successful login or registration, you'll receive a JWT token. Include it in all protected endpoint requests:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Token Expiration
- Default TTL: 60 minutes
- Refresh TTL: 20160 minutes (2 weeks)

To refresh your token before expiration:

```bash
POST /auth/refresh
Headers: Authorization: Bearer {your-current-token}
```

## User Status Flow

1. **Registration** → Status: `pending`, Email: unverified
2. **Email Verification** → Status: `active`, Email: verified
3. **Admin Approval** (for hosts/agencies) → Status: `active`

### User Status Types
- `pending` - Newly registered, awaiting email verification
- `active` - Verified and active account
- `inactive` - Temporarily disabled
- `suspended` - Suspended by admin

## Security Features

- ✅ Password hashing with bcrypt
- ✅ JWT-based authentication
- ✅ Token blacklisting on logout
- ✅ Email verification
- ✅ Password reset with expiring tokens
- ✅ Role-based access control
- ✅ IP tracking on login
- ✅ Soft deletes for users

## Middleware Usage

### Check User Type
```php
Route::middleware(['auth:api', 'user.type:host,admin'])->group(function () {
    // Only hosts and admins can access these routes
});
```

### Ensure Email Verified
```php
Route::middleware(['auth:api', 'verified'])->group(function () {
    // Only verified users can access these routes
});
```

## Database Schema

### Users Table
- Basic user information
- Authentication credentials
- User type and status
- Preferences (currency, language)
- Verification tokens
- Soft deletes

### Host Profiles Table
- Business information
- Verification status
- Response metrics

### Agency Profiles Table
- Agency details
- White-label settings
- Commission/markup configuration
- Verification status

## Next Modules to Implement

1. ✅ Authentication & Authorization (COMPLETED)
2. 🔄 User Profile Management
3. 🔄 Location & Geography
4. 🔄 Hotels Module
5. 🔄 Vacation Rentals Module
6. 🔄 Flights Module
7. 🔄 Experiences & Activities
8. 🔄 Car Rentals
9. 🔄 Bookings & Reservations
10. 🔄 Payments & Transactions
11. 🔄 Reviews & Ratings
12. 🔄 Rewards Program

## Testing with Swagger

1. Open Swagger UI: `http://localhost:8000/documentation`
2. Click "Authorize" button
3. Enter: `Bearer {your-jwt-token}`
4. Test any protected endpoint

## Support & Contact

For issues or questions:
- Email: reservations@pikpakgo.com
- Phone: 800-920-0398

---

**Module Status**: ✅ Authentication Module Complete
**Next Module**: User Profile Management
**Last Updated**: December 2024
