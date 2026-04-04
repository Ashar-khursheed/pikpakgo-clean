<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\Admin\AdminBookingController;
use App\Http\Controllers\Api\Admin\AdminContentController;
use App\Http\Controllers\Api\Admin\AdminPropertyController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\PricingMarkupController;
use App\Http\Controllers\Api\OwnerRezController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('health', function () {
    return response()->json([
        'status'    => 'ok',
        'timestamp' => now(),
        'service'   => 'PikPakGo API v1',
    ]);
});

// ============================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================

Route::prefix('public')->group(function () {

    // Search
    Route::prefix('search')->group(function () {
        Route::post('hotels', [SearchController::class, 'searchHotels']);
        Route::post('properties', [SearchController::class, 'searchProperties']);
        Route::get('destinations', [SearchController::class, 'getDestinations']);
        Route::get('popular-destinations', [SearchController::class, 'getPopularDestinations']);
    });

    // Property details
    Route::prefix('properties')->group(function () {
        Route::get('/', [PropertyController::class, 'index']);
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

    // Public reviews
    Route::get('reviews/{propertyCode}', [ReviewController::class, 'index']);

    // Public content pages (CMS)
    Route::prefix('content')->group(function () {
        Route::get('pages/{slug}', [ContentController::class, 'getPage']);
        Route::get('header', [ContentController::class, 'getHeader']);
        Route::get('footer', [ContentController::class, 'getFooter']);
    });

    // Public settings (site name, currency, etc.)
    Route::get('settings', [AdminSettingsController::class, 'publicSettings']);
});

// ============================================
// AUTHENTICATION ROUTES
// ============================================

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('verify-email/{token}', [AuthController::class, 'verifyEmail']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);

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
    // Guest booking routes (no auth)
    Route::post('guest/create', [BookingController::class, 'createGuestBooking']);
    Route::get('guest/{bookingReference}/verify', [BookingController::class, 'verifyGuestBooking']);
    Route::get('guest/{bookingReference}', [BookingController::class, 'getGuestBooking']);
    Route::post('guest/{bookingReference}/cancel', [BookingController::class, 'cancelGuestBooking']);

    // Authenticated booking routes
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

    // Webhook (no auth, verified by signature)
    Route::post('webhook/authorize-net', [PaymentController::class, 'authorizeNetWebhook']);
});

// ============================================
// PROFILE (Host / Agency)
// ============================================

Route::middleware('auth:api')->prefix('profile')->group(function () {
    Route::get('host', [ProfileController::class, 'getHostProfile']);
    Route::put('host', [ProfileController::class, 'updateHostProfile']);
    Route::get('agency', [ProfileController::class, 'getAgencyProfile']);
    Route::put('agency', [ProfileController::class, 'updateAgencyProfile']);
});

// ============================================
// NOTIFICATIONS (Authenticated)
// ============================================

Route::middleware('auth:api')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::put('read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/', [NotificationController::class, 'destroyAll']);
    Route::put('{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('{id}', [NotificationController::class, 'destroy']);
});

// ============================================
// REVIEWS (Authenticated)
// ============================================

Route::middleware('auth:api')->prefix('reviews')->group(function () {
    Route::get('my', [ReviewController::class, 'myReviews']);
    Route::post('/', [ReviewController::class, 'store']);
    Route::put('{id}', [ReviewController::class, 'update']);
    Route::delete('{id}', [ReviewController::class, 'destroy']);
});

// ============================================
// WISHLIST (Authenticated)
// ============================================

Route::middleware('auth:api')->prefix('wishlist')->group(function () {
    Route::get('/', [WishlistController::class, 'index']);
    Route::post('/', [WishlistController::class, 'store']);
    Route::get('check/{propertyCode}', [WishlistController::class, 'check']);
    Route::delete('{propertyCode}', [WishlistController::class, 'destroy']);
});

// ============================================
// OWNERREZ ROUTES (Direct Channel API)
// ============================================

Route::prefix('ownerrez')->group(function () {
    Route::get('properties', [OwnerRezController::class, 'searchProperties']);
    Route::get('properties/{propertyId}', [OwnerRezController::class, 'getPropertyDetails']);
    Route::post('properties/{propertyId}/availability', [OwnerRezController::class, 'checkAvailability']);
    Route::post('properties/{propertyId}/pricing', [OwnerRezController::class, 'getPricing']);
    Route::post('bookings', [OwnerRezController::class, 'createBooking']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth:api', 'user.type:admin'])->group(function () {

    // Dashboard & Analytics
    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [DashboardController::class, 'getStats']);
        Route::get('revenue', [DashboardController::class, 'getRevenueStats']);
        Route::get('bookings-chart', [DashboardController::class, 'getBookingsChart']);
        Route::get('recent-bookings', [DashboardController::class, 'getRecentBookings']);
        Route::get('top-properties', [DashboardController::class, 'getTopProperties']);
        Route::get('user-growth', [DashboardController::class, 'getUserGrowth']);
    });

    // Booking Management
    Route::prefix('bookings')->group(function () {
        Route::get('export/csv', [AdminBookingController::class, 'exportCsv']);
        Route::get('/', [AdminBookingController::class, 'index']);
        Route::get('{id}', [AdminBookingController::class, 'show']);
        Route::put('{id}/status', [AdminBookingController::class, 'updateStatus']);
    });

    // Property Management
    Route::prefix('properties')->group(function () {
        Route::get('/', [AdminPropertyController::class, 'index']);
        Route::post('sync', [AdminPropertyController::class, 'syncFromAPIs']);
        Route::get('{id}', [AdminPropertyController::class, 'show']);
        Route::put('{id}/status', [AdminPropertyController::class, 'updateStatus']);
    });

    // User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('{id}', [AdminUserController::class, 'show']);
        Route::put('{id}', [AdminUserController::class, 'update']);
        Route::put('{id}/status', [AdminUserController::class, 'updateStatus']);
        Route::put('{id}/role', [AdminUserController::class, 'updateRole']);
        Route::get('{id}/bookings', [AdminUserController::class, 'getUserBookings']);
        Route::post('{id}/reset-password', [AdminUserController::class, 'resetPassword']);
        Route::delete('{id}', [AdminUserController::class, 'destroy']);
    });

    // Pricing Markup Management
    Route::prefix('pricing-markups')->group(function () {
        Route::get('/', [PricingMarkupController::class, 'index']);
        Route::post('/', [PricingMarkupController::class, 'store']);
        Route::post('calculate', [PricingMarkupController::class, 'calculateMarkup']);
        Route::post('set-default', [PricingMarkupController::class, 'setDefault']);
        Route::get('{id}', [PricingMarkupController::class, 'show']);
        Route::put('{id}', [PricingMarkupController::class, 'update']);
        Route::put('{id}/toggle-status', [PricingMarkupController::class, 'toggleStatus']);
        Route::delete('{id}', [PricingMarkupController::class, 'destroy']);
    });

    // Settings Management
    Route::prefix('settings')->group(function () {
        Route::get('/', [AdminSettingsController::class, 'index']);
        Route::put('/', [AdminSettingsController::class, 'update']);
        Route::put('{key}', [AdminSettingsController::class, 'updateSingle']);
    });

    // Content Management (CMS)
    Route::prefix('content')->group(function () {
        Route::get('/', [AdminContentController::class, 'index']);
        Route::post('/', [AdminContentController::class, 'store']);
        Route::get('{id}', [AdminContentController::class, 'show']);
        Route::put('{id}', [AdminContentController::class, 'update']);
        Route::delete('{id}', [AdminContentController::class, 'destroy']);
    });

    // Host & Agency Management
    Route::get('hosts', [ProfileController::class, 'adminListHosts']);
    Route::put('hosts/{id}/verify', [ProfileController::class, 'adminVerifyHost']);
    Route::get('agencies', [ProfileController::class, 'adminListAgencies']);

    // Review Moderation
    Route::prefix('reviews')->group(function () {
        Route::get('/', [AdminReviewController::class, 'index']);
        Route::put('{id}/approve', [AdminReviewController::class, 'approve']);
        Route::put('{id}/reject', [AdminReviewController::class, 'reject']);
        Route::put('{id}/reply', [AdminReviewController::class, 'reply']);
        Route::delete('{id}', [AdminReviewController::class, 'destroy']);
    });
});
