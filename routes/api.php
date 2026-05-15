<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\Admin\AdminBookingController;
use App\Http\Controllers\Api\Admin\FinancialController;
use App\Http\Controllers\Api\Admin\AdminContentController;
use App\Http\Controllers\Api\Admin\AdminPropertyController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminBlogController;
use App\Http\Controllers\Api\Admin\AdminSeoController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminPropertyFeesController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\PricingMarkupController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\PropertyApprovalController;
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
        Route::get('autocomplete', [SearchController::class, 'autocomplete']);
    });

    // Property details & discovery
    Route::prefix('properties')->group(function () {
        // Static list-type routes BEFORE {id} to prevent routing conflicts
        Route::get('featured',     [PropertyController::class, 'featured']);
        Route::get('new-arrivals', [PropertyController::class, 'newArrivals']);
        Route::get('top-rated',    [PropertyController::class, 'topRated']);
        Route::get('amenities',    [PropertyController::class, 'amenities']);
        Route::get('types',        [PropertyController::class, 'types']);

        Route::get('/', [PropertyController::class, 'index']);
        Route::get('{id}', [PropertyController::class, 'show']);
        Route::post('{id}/check-availability', [PropertyController::class, 'checkAvailability']);
        Route::post('{id}/get-pricing', [PropertyController::class, 'getPricing']);
        Route::get('{id}/reviews', [PropertyController::class, 'getReviews']);
        Route::get('{id}/similar', [PropertyController::class, 'getSimilarProperties']);
        Route::get('{id}/calendar', [PropertyController::class, 'calendar']);
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
        Route::get('header',       [ContentController::class, 'getHeader']);
        Route::get('footer',       [ContentController::class, 'getFooter']);
        Route::get('nav',          [ContentController::class, 'getNav']);
    });

    // Public SEO resolution — call before rendering any frontend page
    Route::prefix('seo')->group(function () {
        Route::get('/',                         [ContentController::class, 'getSeo']);          // ?path=/about-us OR ?slug=about-us
        Route::get('property/{propertyCode}',   [ContentController::class, 'getPropertySeo']); // auto-generated property SEO
    });

    // Public Blog
    Route::prefix('blog')->group(function () {
        Route::get('posts',                  [BlogController::class, 'index']);
        Route::get('posts/{slug}',           [BlogController::class, 'show']);
        Route::get('categories',             [BlogController::class, 'categories']);
        Route::get('categories/{slug}',      [BlogController::class, 'categoryShow']);
        Route::get('featured',               [BlogController::class, 'featured']);
        Route::get('recent',                 [BlogController::class, 'recent']);
        Route::get('tags',                   [BlogController::class, 'tags']);
    });

    // Public settings (site name, currency, etc.)
    Route::get('settings', [AdminSettingsController::class, 'publicSettings']);

    // Site meta (currencies, languages, property types, sort options)
    Route::get('site-info', [PublicController::class, 'siteInfo']);

    // Contact & Newsletter
    Route::post('contact', [PublicController::class, 'contact']);
    Route::post('newsletter/subscribe', [PublicController::class, 'newsletterSubscribe']);
    Route::get('newsletter/unsubscribe/{token}', [PublicController::class, 'newsletterUnsubscribe']);

    // FAQ
    Route::get('faqs', [PublicController::class, 'faqs']);

    // Booking tracker (no auth — for confirmation/status page)
    Route::get('bookings/{bookingReference}/track', [PublicController::class, 'trackBooking']);
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
    Route::get('/',        [NotificationController::class, 'index']);
    Route::get('badge',    [NotificationController::class, 'badge']);
    Route::put('read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/',     [NotificationController::class, 'destroyAll']);
    Route::put('{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('{id}',   [NotificationController::class, 'destroy']);
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
        Route::post('{id}/refund', [AdminBookingController::class, 'refund']);
    });

    // Property Management
    Route::prefix('properties')->group(function () {
        Route::get('/', [AdminPropertyController::class, 'index']);
        Route::post('/', [AdminPropertyController::class, 'store']);
        Route::post('sync', [AdminPropertyController::class, 'syncFromAPIs']);
        Route::get('{id}', [AdminPropertyController::class, 'show']);
        Route::put('{id}', [AdminPropertyController::class, 'update']);
        Route::put('{id}/status', [AdminPropertyController::class, 'updateStatus']);
        Route::delete('{id}', [AdminPropertyController::class, 'destroy']);

        // Property Approvals
        Route::prefix('approvals')->group(function () {
            Route::get('pending', [PropertyApprovalController::class, 'pending']);
            Route::post('{id}/approve', [PropertyApprovalController::class, 'approve']);
            Route::post('{id}/reject', [PropertyApprovalController::class, 'reject']);
        });

        // Property Fees (nested under properties)
        Route::get('{propertyId}/fees', [AdminPropertyFeesController::class, 'index']);
        Route::post('{propertyId}/fees', [AdminPropertyFeesController::class, 'store']);
        Route::post('{propertyId}/fees/bulk', [AdminPropertyFeesController::class, 'bulkReplace']);
        Route::get('{propertyId}/fees/preview', [AdminPropertyFeesController::class, 'preview']);
        Route::put('{propertyId}/fees/{feeId}', [AdminPropertyFeesController::class, 'update']);
        Route::delete('{propertyId}/fees/{feeId}', [AdminPropertyFeesController::class, 'destroy']);
    });

    // User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::post('/', [AdminUserController::class, 'store']);
        Route::get('{id}', [AdminUserController::class, 'show']);
        Route::put('{id}', [AdminUserController::class, 'update']);
        Route::put('{id}/status', [AdminUserController::class, 'updateStatus']);
        Route::put('{id}/role', [AdminUserController::class, 'updateRole']);
        Route::get('{id}/bookings', [AdminUserController::class, 'getUserBookings']);
        Route::post('{id}/reset-password', [AdminUserController::class, 'resetPassword']);
        Route::delete('{id}', [AdminUserController::class, 'destroy']);
    });

    // Role & Permission Management
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::put('{id}', [RoleController::class, 'update']);
        Route::delete('{id}', [RoleController::class, 'destroy']);
        Route::post('{id}/permissions', [RoleController::class, 'syncPermissions']);
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::delete('{id}', [PermissionController::class, 'destroy']);
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

    // Content Management (CMS + Page Builder)
    Route::prefix('content')->group(function () {
        Route::get('/',              [AdminContentController::class, 'index']);
        Route::post('/',             [AdminContentController::class, 'store']);
        Route::post('reorder',       [AdminContentController::class, 'reorder']);
        Route::get('{id}',           [AdminContentController::class, 'show']);
        Route::put('{id}',           [AdminContentController::class, 'update']);
        Route::delete('{id}',        [AdminContentController::class, 'destroy']);
        Route::put('{id}/restore',   [AdminContentController::class, 'restore']);
    });

    // Blog Management
    Route::prefix('blog')->group(function () {
        // Categories
        Route::get('categories',                    [AdminBlogController::class, 'categoryIndex']);
        Route::post('categories',                   [AdminBlogController::class, 'categoryStore']);
        Route::get('categories/{id}',               [AdminBlogController::class, 'categoryShow']);
        Route::put('categories/{id}',               [AdminBlogController::class, 'categoryUpdate']);
        Route::delete('categories/{id}',            [AdminBlogController::class, 'categoryDestroy']);
        // Posts
        Route::get('posts',                         [AdminBlogController::class, 'postIndex']);
        Route::post('posts',                        [AdminBlogController::class, 'postStore']);
        Route::get('posts/{id}',                    [AdminBlogController::class, 'postShow']);
        Route::put('posts/{id}',                    [AdminBlogController::class, 'postUpdate']);
        Route::delete('posts/{id}',                 [AdminBlogController::class, 'postDestroy']);
        Route::put('posts/{id}/restore',            [AdminBlogController::class, 'postRestore']);
        Route::put('posts/{id}/status',             [AdminBlogController::class, 'postUpdateStatus']);
        Route::put('posts/{id}/toggle-featured',    [AdminBlogController::class, 'postToggleFeatured']);
    });

    // SEO Management — per-route SEO configs
    Route::prefix('seo')->group(function () {
        Route::get('/',                             [AdminSeoController::class, 'index']);
        Route::post('/',                            [AdminSeoController::class, 'store']);
        Route::post('bulk-update',                  [AdminSeoController::class, 'bulkUpdate']);
        Route::get('routes',                        [AdminSeoController::class, 'routes']);
        Route::get('property/{propertyCode}',       [AdminSeoController::class, 'propertySeoPeview']);
        Route::get('{id}',                          [AdminSeoController::class, 'show']);
        Route::put('{id}',                          [AdminSeoController::class, 'update']);
        Route::delete('{id}',                       [AdminSeoController::class, 'destroy']);
    });

    // Contact Forms & Newsletter (Admin)
    Route::get('contact-forms', [PublicController::class, 'adminListContacts']);
    Route::put('contact-forms/{id}/reply', [PublicController::class, 'adminReplyContact']);
    Route::get('newsletter-subscribers', [PublicController::class, 'adminListSubscribers']);

    // Host & Agency Management
    Route::get('hosts', [ProfileController::class, 'adminListHosts']);
    Route::put('hosts/{id}/verify', [ProfileController::class, 'adminVerifyHost']);
    Route::get('agencies', [ProfileController::class, 'adminListAgencies']);
    Route::put('agencies/{id}/verify', [ProfileController::class, 'adminVerifyAgency']);

    // Financial Reports & Provider Payout Management
    Route::prefix('financial')->group(function () {
        Route::get('overview',              [FinancialController::class, 'overview']);
        Route::get('payouts',               [FinancialController::class, 'payouts']);
        Route::get('profit-by-property',    [FinancialController::class, 'profitByProperty']);
        Route::post('payouts/{id}/retry',   [FinancialController::class, 'retryPayout']);
        Route::post('payouts/retry-all-failed', [FinancialController::class, 'retryAllFailed']);
    });

    // Review Moderation
    Route::prefix('reviews')->group(function () {
        Route::get('/', [AdminReviewController::class, 'index']);
        Route::put('{id}/approve', [AdminReviewController::class, 'approve']);
        Route::put('{id}/reject', [AdminReviewController::class, 'reject']);
        Route::put('{id}/reply', [AdminReviewController::class, 'reply']);
        Route::delete('{id}', [AdminReviewController::class, 'destroy']);
    });
});
