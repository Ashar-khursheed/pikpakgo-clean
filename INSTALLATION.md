# PikPakGo API - Complete Installation Guide

## 📦 What's Included

This package contains a complete Laravel 11 project with:

✅ **Authentication Module** (Fully Implemented)
- User Registration (Customer, Host, Agency)
- Login/Logout with JWT
- Email Verification
- Password Reset
- Token Refresh
- Role-based Access Control

✅ **Database Structure**
- Users table with all fields
- Host profiles table
- Agency profiles table
- Password reset tokens

✅ **Complete Swagger/OpenAPI Documentation**
- All endpoints documented
- Interactive API testing UI
- Request/response examples

✅ **Security Features**
- JWT authentication
- Role-based middleware
- Email verification middleware
- Password hashing
- Token blacklisting

---

## 🚀 Quick Start Installation

### Prerequisites

Make sure you have installed:
- PHP 8.2 or higher
- Composer
- MySQL 8.0 or higher
- Node.js & NPM (optional, for frontend)

### Step 1: Extract & Navigate

```bash
unzip pikpakgo-api.zip
cd pikpakgo-api
```

### Step 2: Install Dependencies

```bash
composer install
```

This will install all required packages:
- Laravel Framework 11.x
- JWT Auth (tymon/jwt-auth)
- L5 Swagger (API Documentation)
- And all other dependencies

### Step 3: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret
```

### Step 4: Configure Database

Open `.env` file and update database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pikpakgo
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database:

```bash
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE pikpakgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### Step 5: Run Migrations

```bash
php artisan migrate
```

This will create all tables:
- users
- host_profiles
- agency_profiles
- password_reset_tokens

### Step 6: Generate Swagger Documentation

```bash
php artisan l5-swagger:generate
```

### Step 7: Start Development Server

```bash
php artisan serve
```

Your API is now running at: **http://localhost:8000**

---

## 📖 Access API Documentation

Open your browser and visit:

```
http://localhost:8000/documentation
```

You'll see the complete Swagger UI with all endpoints, request/response examples, and the ability to test APIs directly!

---

## 🧪 Testing the API

### Method 1: Using Swagger UI (Recommended)

1. Go to `http://localhost:8000/documentation`
2. Click on any endpoint to expand it
3. Click "Try it out"
4. Fill in the request body
5. Click "Execute"

### Method 2: Using cURL

#### Register a Customer

```bash
curl -X POST http://localhost:8000/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "user_type": "customer"
  }'
```

#### Login

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

Save the token from response!

#### Get Current User (Protected Endpoint)

```bash
curl -X GET http://localhost:8000/auth/me \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE"
```

---

## 📁 Project Structure

```
pikpakgo-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── AuthController.php     # All auth endpoints
│   │   └── Middleware/
│   │       ├── CheckUserType.php           # Role-based access
│   │       └── EnsureEmailIsVerified.php   # Email verification check
│   └── Models/
│       ├── User.php                        # User model with JWT
│       ├── HostProfile.php                 # Host profile model
│       └── AgencyProfile.php               # Agency profile model
├── config/
│   ├── auth.php                            # JWT guard configuration
│   ├── jwt.php                             # JWT settings
│   └── l5-swagger.php                      # Swagger configuration
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_users_table.php
│       ├── 2024_01_01_000002_create_host_profiles_table.php
│       ├── 2024_01_01_000003_create_agency_profiles_table.php
│       └── 2024_01_01_000004_create_password_reset_tokens_table.php
├── routes/
│   ├── api.php                             # API routes
│   ├── web.php                             # Web routes
│   └── console.php                         # Console commands
├── .env.example                            # Environment template
├── composer.json                           # PHP dependencies
└── README.md                               # Main documentation
```

---

## 🔐 Available API Endpoints

### Public Endpoints (No Authentication Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user (customer/host/agency) |
| POST | `/auth/login` | Login and get JWT token |
| POST | `/auth/forgot-password` | Request password reset link |
| POST | `/auth/reset-password` | Reset password with token |
| POST | `/auth/verify-email/{token}` | Verify email address |
| POST | `/auth/resend-verification` | Resend verification email |

### Protected Endpoints (Requires JWT Token)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/logout` | Logout and invalidate token |
| POST | `/auth/refresh` | Refresh JWT token |
| GET | `/auth/me` | Get current authenticated user |
| POST | `/auth/change-password` | Change user password |

---

## 👥 User Types

The system supports 4 user types:

1. **Customer** - Regular travelers who book travel services
2. **Host** - Property owners who list vacation rentals
3. **Agency** - B2B travel agency partners with white-label access
4. **Admin** - System administrators

### Register Different User Types

#### Customer Registration
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "user_type": "customer"
}
```

#### Host Registration
```json
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
        "bio": "Professional property management"
    }
}
```

#### Agency Registration
```json
{
    "first_name": "Mike",
    "last_name": "Johnson",
    "email": "mike@agency.com",
    "password": "password123",
    "password_confirmation": "password123",
    "user_type": "agency",
    "agency_profile": {
        "agency_name": "Global Travel Agency",
        "agency_registration_number": "AGY123456",
        "tax_id": "TAX123456",
        "website": "https://agency.com"
    }
}
```

---

## 🔒 Using JWT Authentication

### How JWT Works

1. User logs in → Server returns JWT token
2. Client stores token (localStorage/cookies)
3. Client sends token in Authorization header for protected requests
4. Server validates token and processes request

### Token Format

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Token Expiration

- **Access Token**: 60 minutes (configurable in .env: `JWT_TTL=60`)
- **Refresh Token**: 2 weeks (configurable in .env: `JWT_REFRESH_TTL=20160`)

### Refresh Token Before Expiration

```bash
curl -X POST http://localhost:8000/auth/refresh \
  -H "Authorization: Bearer YOUR_CURRENT_TOKEN"
```

---

## 🛡️ Middleware Usage

### Protect Routes by User Type

```php
// Only hosts can access
Route::middleware(['auth:api', 'user.type:host'])->group(function () {
    Route::get('/properties', [PropertyController::class, 'index']);
});

// Hosts and admins can access
Route::middleware(['auth:api', 'user.type:host,admin'])->group(function () {
    Route::post('/properties', [PropertyController::class, 'store']);
});
```

### Require Email Verification

```php
Route::middleware(['auth:api', 'verified'])->group(function () {
    Route::post('/bookings', [BookingController::class, 'store']);
});
```

---

## 📧 Email Configuration (Optional)

For email verification and password reset features, configure your mail settings in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="reservations@pikpakgo.com"
MAIL_FROM_NAME="PikPakGo"
```

For development, you can use [Mailtrap](https://mailtrap.io/) or [MailHog](https://github.com/mailhog/MailHog).

---

## 🐛 Troubleshooting

### Issue: "Class 'Tymon\JWTAuth\...' not found"

**Solution:**
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Issue: "SQLSTATE[HY000] [2002] Connection refused"

**Solution:** Make sure MySQL is running and credentials in `.env` are correct.

```bash
# Check MySQL status
sudo systemctl status mysql

# Start MySQL
sudo systemctl start mysql
```

### Issue: Swagger documentation not showing

**Solution:**
```bash
php artisan l5-swagger:generate
php artisan config:clear
```

### Issue: "JWT Secret not set"

**Solution:**
```bash
php artisan jwt:secret
```

---

## 🔄 Next Modules to Implement

The authentication module is complete! Here are the upcoming modules:

1. ✅ **Authentication & Authorization** (COMPLETED)
2. 🔄 **User Profile Management**
3. 🔄 **Location & Geography**
4. 🔄 **Hotels Module**
5. 🔄 **Vacation Rentals Module**
6. 🔄 **Flights Integration**
7. 🔄 **Car Rentals**
8. 🔄 **Experiences & Activities**
9. 🔄 **Booking System**
10. 🔄 **Payment Gateway (Stripe)**
11. 🔄 **Reviews & Ratings**
12. 🔄 **Rewards Program**

---

## 📞 Support

For questions or issues:
- **Email**: reservations@pikpakgo.com
- **Phone**: 800-920-0398
- **Documentation**: http://localhost:8000/documentation

---

## 📝 License

This project is proprietary software for PikPakGo.

---

## ✅ Verification Checklist

After installation, verify everything is working:

- [ ] `composer install` completed successfully
- [ ] `.env` file configured
- [ ] Database created and connected
- [ ] `php artisan migrate` ran successfully
- [ ] JWT secret generated
- [ ] Swagger documentation accessible at `/documentation`
- [ ] Can register a new user via API
- [ ] Can login and receive JWT token
- [ ] Can access protected endpoint with token

---

**Ready to develop!** 🚀

Start testing your APIs at: http://localhost:8000/documentation
