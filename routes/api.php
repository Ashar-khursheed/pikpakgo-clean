<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Admin\AdminBookingController;
use App\Http\Controllers\Api\Admin\AdminPropertyController;
use App\Http\Controllers\Api\Admin\PricingMarkupController;
use App\Http\Controllers\Api\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes - Website (Public & User)
|--------------------------------------------------------------------------
*/

// Health check
Route::get('health', function() {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'PikPakGo API v1'
    ]);
});

// ============================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================

Route::prefix('public')->group(function () {
    
    // Search endpoints - COMPLETELY PUBLIC
    Route::prefix('search')->group(function () {
        Route::post('hotels', [SearchController::class, 'searchHotels']);
        Route::post('properties', [SearchController::class, 'searchProperties']);
        Route::get('destinations', [SearchController::class, 'getDestinations']);
        Route::get('popular-destinations', [SearchController::class, 'getPopularDestinations']);
    });
    
    // Property details - PUBLIC
    Route::prefix('properties')->group(function () {
        Route::get('{id}', [PropertyController::class, 'show']);
        Route::post('{id}/check-availability', [PropertyController::class, 'checkAvailability']);
        Route::post('{id}/get-pricing', [PropertyController::class, 'getPricing']);
        Route::get('{id}/reviews', [PropertyController::class, 'getReviews']);
        Route::get('{id}/similar', [PropertyController::class, 'getSimilarProperties']);
    });
    
    // Guest session management
    Route::prefix('guest')->group(function () {
        Route::post('session/create', [GuestController::class, 'createSession']);
        Route::post('session/update', [GuestController::class, 'updateSession']);
        Route::get('session/{sessionId}', [GuestController::class, 'getSession']);
    });
});

// ============================================
// AUTHENTICATION ROUTES
// ============================================

Route::prefix('auth')->group(function () {
    // Public auth routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('verify-email/{token}', [AuthController::class, 'verifyEmail']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);
    
    // Protected auth routes
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

// ============================================
// BOOKING ROUTES (Guest + Authenticated)
// ============================================

Route::prefix('bookings')->group(function () {
    
    // Guest booking routes (no auth required)
    Route::post('guest/create', [BookingController::class, 'createGuestBooking']);
    Route::get('guest/{bookingReference}/verify', [BookingController::class, 'verifyGuestBooking']);
    Route::get('guest/{bookingReference}', [BookingController::class, 'getGuestBooking']);
    Route::post('guest/{bookingReference}/cancel', [BookingController::class, 'cancelGuestBooking']);
    
    // Authenticated user booking routes
    Route::middleware('auth:api')->group(function () {
        Route::get('/', [BookingController::class, 'getUserBookings']);
        Route::post('/', [BookingController::class, 'createBooking']);
        Route::get('{bookingReference}', [BookingController::class, 'getBooking']);
        Route::post('{bookingReference}/cancel', [BookingController::class, 'cancelBooking']);
        Route::get('{bookingReference}/invoice', [BookingController::class, 'downloadInvoice']);
    });
});

// ============================================
// PAYMENT ROUTES
// ============================================

Route::prefix('payments')->group(function () {
    
    // Guest payment routes
    Route::post('guest/process', [PaymentController::class, 'processGuestPayment']);
    Route::get('guest/{transactionId}/status', [PaymentController::class, 'getGuestPaymentStatus']);
    
    // Authenticated payment routes
    Route::middleware('auth:api')->group(function () {
        Route::post('process', [PaymentController::class, 'processPayment']);
        Route::get('{transactionId}/status', [PaymentController::class, 'getPaymentStatus']);
        Route::get('history', [PaymentController::class, 'getPaymentHistory']);
    });
    
    // Webhooks (no auth, but verified by signature)
    Route::post('webhook/authorize-net', [PaymentController::class, 'authorizeNetWebhook']);
});

/*
|--------------------------------------------------------------------------
| API Routes - Admin Panel
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {
    
    // Dashboard & Analytics
    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [DashboardController::class, 'getStats']);
        Route::get('revenue', [DashboardController::class, 'getRevenueStats']);
        Route::get('bookings/chart', [DashboardController::class, 'getBookingsChart']);
        Route::get('recent-bookings', [DashboardController::class, 'getRecentBookings']);
        Route::get('top-properties', [DashboardController::class, 'getTopProperties']);
    });
    
    // Booking Management
    Route::prefix('bookings')->group(function () {
        Route::get('/', [AdminBookingController::class, 'index']);
        Route::get('{id}', [AdminBookingController::class, 'show']);
        Route::put('{id}/status', [AdminBookingController::class, 'updateStatus']);
        Route::post('{id}/refund', [AdminBookingController::class, 'processRefund']);
        Route::put('{id}/notes', [AdminBookingController::class, 'updateNotes']);
        Route::get('export/csv', [AdminBookingController::class, 'exportCSV']);
        Route::get('export/pdf', [AdminBookingController::class, 'exportPDF']);
    });
    
    // Property Management
    Route::prefix('properties')->group(function () {
        Route::get('/', [AdminPropertyController::class, 'index']);
        Route::get('{id}', [AdminPropertyController::class, 'show']);
        Route::post('sync', [AdminPropertyController::class, 'syncFromAPIs']);
        Route::post('{id}/sync', [AdminPropertyController::class, 'syncSingle']);
        Route::put('{id}/status', [AdminPropertyController::class, 'updateStatus']);
        Route::put('{id}/featured', [AdminPropertyController::class, 'toggleFeatured']);
        Route::delete('{id}', [AdminPropertyController::class, 'destroy']);
    });
    
    // Pricing Markup Management
    Route::prefix('pricing-markups')->group(function () {
        Route::get('/', [PricingMarkupController::class, 'index']);
        Route::post('/', [PricingMarkupController::class, 'store']);
        Route::get('{id}', [PricingMarkupController::class, 'show']);
        Route::put('{id}', [PricingMarkupController::class, 'update']);
        Route::delete('{id}', [PricingMarkupController::class, 'destroy']);
        Route::put('{id}/toggle-status', [PricingMarkupController::class, 'toggleStatus']);
        Route::post('set-default', [PricingMarkupController::class, 'setDefault']);
        Route::post('calculate', [PricingMarkupController::class, 'calculateMarkup']); // Test calculator
    });
    
    // User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminBookingController::class, 'getUsers']);
        Route::get('{id}', [AdminBookingController::class, 'getUser']);
        Route::put('{id}/status', [AdminBookingController::class, 'updateUserStatus']);
        Route::get('{id}/bookings', [AdminBookingController::class, 'getUserBookings']);
    });
    
    // Payment Management
    Route::prefix('payments')->group(function () {
        Route::get('/', [AdminBookingController::class, 'getPayments']);
        Route::get('{id}', [AdminBookingController::class, 'getPayment']);
        Route::get('export/csv', [AdminBookingController::class, 'exportPaymentsCSV']);
    });
    
    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [DashboardController::class, 'getSettings']);
        Route::put('/', [DashboardController::class, 'updateSettings']);
        Route::get('api-config', [DashboardController::class, 'getAPIConfig']);
        Route::put('api-config', [DashboardController::class, 'updateAPIConfig']);
    });
});
