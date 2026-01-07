<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HotelbedsController;
use App\Http\Controllers\Api\OwnerRezController;
use App\Http\Controllers\Api\PerformanceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check (public)
Route::get('health', [PerformanceController::class, 'health']);

Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('verify-email/{token}', [AuthController::class, 'verifyEmail']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);

    // Protected routes
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

// Performance monitoring (protected)
Route::prefix('performance')->middleware('auth:api')->group(function () {
    Route::get('cache-stats', [PerformanceController::class, 'cacheStats']);
    Route::get('database-stats', [PerformanceController::class, 'databaseStats']);
    Route::post('clear-cache', [PerformanceController::class, 'clearCache']);
});

// Hotelbeds API (protected)
Route::prefix('hotelbeds')->middleware('auth:api')->group(function () {
    Route::post('search', [HotelbedsController::class, 'searchHotels']);
    Route::get('hotels/{hotelCode}', [HotelbedsController::class, 'getHotelDetails']);
    Route::post('check-availability', [HotelbedsController::class, 'checkAvailability']);
    Route::post('bookings', [HotelbedsController::class, 'createBooking']);
    Route::get('bookings/{bookingReference}', [HotelbedsController::class, 'getBooking']);
    Route::delete('bookings/{bookingReference}', [HotelbedsController::class, 'cancelBooking']);
});

// OwnerRez API (protected)
Route::prefix('ownerrez')->middleware('auth:api')->group(function () {
    Route::get('properties', [OwnerRezController::class, 'searchProperties']);
    Route::get('properties/{propertyId}', [OwnerRezController::class, 'getPropertyDetails']);
    Route::post('properties/{propertyId}/availability', [OwnerRezController::class, 'checkAvailability']);
    Route::post('properties/{propertyId}/pricing', [OwnerRezController::class, 'getPricing']);
    Route::get('properties/{propertyId}/reviews', [OwnerRezController::class, 'getReviews']);
    Route::post('bookings', [OwnerRezController::class, 'createBooking']);
    Route::get('bookings/{bookingId}', [OwnerRezController::class, 'getBooking']);
    Route::put('bookings/{bookingId}', [OwnerRezController::class, 'updateBooking']);
    Route::delete('bookings/{bookingId}', [OwnerRezController::class, 'cancelBooking']);
});
