<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\GuestSession;
use App\Services\HotelbedsService;
use App\Services\OwnerRezService;
use App\Services\PricingMarkupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Bookings",
 *     description="Booking management for both guest and authenticated users"
 * )
 */
class BookingController extends Controller
{
    protected $hotelbedsService;
    protected $ownerrezService;
    protected $pricingService;
    
    public function __construct(
        HotelbedsService $hotelbedsService,
        OwnerRezService $ownerrezService,
        PricingMarkupService $pricingService
    ) {
        $this->hotelbedsService = $hotelbedsService;
        $this->ownerrezService = $ownerrezService;
        $this->pricingService = $pricingService;
    }
    
    /**
     * Create a guest booking (no authentication required)
     */
    public function createGuestBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_session_id' => 'required|string',
            'provider' => 'required|in:hotelbeds,ownerrez',
            'property_code' => 'required|string',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'total_adults' => 'required|integer|min:1',
            'total_children' => 'nullable|integer|min:0',
            'total_rooms' => 'required|integer|min:1',
            'room_details' => 'nullable|array',
            
            // Guest holder information
            'holder_first_name' => 'required|string|max:255',
            'holder_last_name' => 'required|string|max:255',
            'holder_email' => 'required|email',
            'holder_phone' => 'required|string',
            'holder_country_code' => 'nullable|string',
            
            // Property details
            'property_name' => 'required|string',
            'property_address' => 'nullable|string',
            'property_city' => 'nullable|string',
            'property_country' => 'nullable|string',
            
            // Pricing
            'base_price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            
            'special_requests' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            // Verify guest session exists
            $guestSession = GuestSession::where('session_id', $request->guest_session_id)->first();
            if (!$guestSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid guest session'
                ], 400);
            }
            
            // Calculate nights
            $checkIn = \Carbon\Carbon::parse($request->check_in_date);
            $checkOut = \Carbon\Carbon::parse($request->check_out_date);
            $nights = $checkIn->diffInDays($checkOut);
            
            // Calculate markup
            $markupData = $this->pricingService->calculateMarkup([
                'base_price' => $request->base_price,
                'provider' => $request->provider,
                'property_type' => $request->property_type ?? 'hotel',
                'destination_code' => $request->destination_code,
                'check_in_date' => $request->check_in_date,
            ]);
            
            // Generate unique booking reference
            $bookingReference = 'PKG-' . strtoupper(Str::random(10));
            
            // Create booking in database
            $booking = Booking::create([
                'booking_reference' => $bookingReference,
                'provider' => $request->provider,
                'guest_session_id' => $request->guest_session_id,
                'guest_email' => $request->holder_email,
                'guest_phone' => $request->holder_phone,
                
                'holder_first_name' => $request->holder_first_name,
                'holder_last_name' => $request->holder_last_name,
                'holder_email' => $request->holder_email,
                'holder_phone' => $request->holder_phone,
                'holder_country_code' => $request->holder_country_code,
                
                'property_code' => $request->property_code,
                'property_name' => $request->property_name,
                'property_address' => $request->property_address,
                'property_city' => $request->property_city,
                'property_country' => $request->property_country,
                
                'check_in_date' => $request->check_in_date,
                'check_out_date' => $request->check_out_date,
                'nights' => $nights,
                
                'total_rooms' => $request->total_rooms,
                'total_adults' => $request->total_adults,
                'total_children' => $request->total_children ?? 0,
                'room_details' => $request->room_details,
                
                'base_price' => $request->base_price,
                'markup_amount' => $markupData['markup_amount'],
                'markup_percentage' => $markupData['markup_percentage'],
                'total_price' => $markupData['final_price'],
                'currency' => $request->currency ?? 'USD',
                
                'special_requests' => $request->special_requests,
                'booking_status' => 'pending',
                'payment_status' => 'pending'
            ]);
            
            // Update guest session
            $guestSession->increment('booking_count');
            $guestSession->update([
                'email' => $request->holder_email,
                'first_name' => $request->holder_first_name,
                'last_name' => $request->holder_last_name,
                'phone' => $request->holder_phone,
                'last_activity_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'booking' => $booking,
                    'booking_reference' => $bookingReference,
                    'verification_email_sent' => true
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Guest booking creation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the booking',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Create authenticated user booking
     */
    public function createBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:hotelbeds,ownerrez',
            'property_code' => 'required|string',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'total_adults' => 'required|integer|min:1',
            'total_children' => 'nullable|integer|min:0',
            'total_rooms' => 'required|integer|min:1',
            'room_details' => 'nullable|array',
            'property_name' => 'required|string',
            'base_price' => 'required|numeric|min:0',
            'special_requests' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            $user = auth()->user();
            
            $checkIn = \Carbon\Carbon::parse($request->check_in_date);
            $checkOut = \Carbon\Carbon::parse($request->check_out_date);
            $nights = $checkIn->diffInDays($checkOut);
            
            $markupData = $this->pricingService->calculateMarkup([
                'base_price' => $request->base_price,
                'provider' => $request->provider,
                'property_type' => $request->property_type ?? 'hotel',
                'destination_code' => $request->destination_code,
                'check_in_date' => $request->check_in_date,
            ]);
            
            $bookingReference = 'PKG-' . strtoupper(Str::random(10));
            
            $booking = Booking::create([
                'booking_reference' => $bookingReference,
                'provider' => $request->provider,
                'user_id' => $user->id,
                
                'holder_first_name' => $user->first_name,
                'holder_last_name' => $user->last_name,
                'holder_email' => $user->email,
                'holder_phone' => $user->phone ?? $request->holder_phone,
                'holder_country_code' => $user->phone_country_code,
                
                'property_code' => $request->property_code,
                'property_name' => $request->property_name,
                'property_address' => $request->property_address,
                'property_city' => $request->property_city,
                'property_country' => $request->property_country,
                
                'check_in_date' => $request->check_in_date,
                'check_out_date' => $request->check_out_date,
                'nights' => $nights,
                
                'total_rooms' => $request->total_rooms,
                'total_adults' => $request->total_adults,
                'total_children' => $request->total_children ?? 0,
                'room_details' => $request->room_details,
                
                'base_price' => $request->base_price,
                'markup_amount' => $markupData['markup_amount'],
                'markup_percentage' => $markupData['markup_percentage'],
                'total_price' => $markupData['final_price'],
                'currency' => $request->currency ?? 'USD',
                
                'special_requests' => $request->special_requests,
                'booking_status' => 'pending',
                'payment_status' => 'pending'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'booking' => $booking,
                    'booking_reference' => $bookingReference
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('User booking creation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the booking',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Get guest booking by reference
     */
    public function getGuestBooking($bookingReference)
    {
        try {
            $booking = Booking::where('booking_reference', $bookingReference)
                ->whereNotNull('guest_session_id')
                ->first();
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $booking
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get guest booking error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Get user bookings (authenticated)
     */
    public function getUserBookings(Request $request)
    {
        try {
            $user = auth()->user();
            
            $bookings = Booking::where('user_id', $user->id)
                ->when($request->status, function($query, $status) {
                    return $query->where('booking_status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 10);
            
            return response()->json([
                'success' => true,
                'data' => $bookings
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get user bookings error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Get single booking (authenticated)
     */
    public function getBooking($bookingReference)
    {
        try {
            $user = auth()->user();
            
            $booking = Booking::where('booking_reference', $bookingReference)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $booking
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get booking error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Cancel guest booking
     */
    public function cancelGuestBooking(Request $request, $bookingReference)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'reason' => 'nullable|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            $booking = Booking::where('booking_reference', $bookingReference)
                ->where('guest_email', $request->email)
                ->first();
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found or email mismatch'
                ], 404);
            }
            
            if ($booking->booking_status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking already cancelled'
                ], 400);
            }
            
            $booking->update([
                'booking_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => 'guest',
                'cancellation_reason' => $request->reason
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => $booking
            ]);
            
        } catch (\Exception $e) {
            Log::error('Cancel guest booking error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Cancel user booking
     */
    public function cancelBooking(Request $request, $bookingReference)
    {
        try {
            $user = auth()->user();
            
            $booking = Booking::where('booking_reference', $bookingReference)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }
            
            if ($booking->booking_status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking already cancelled'
                ], 400);
            }
            
            $booking->update([
                'booking_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => 'user',
                'cancellation_reason' => $request->reason
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => $booking
            ]);
            
        } catch (\Exception $e) {
            Log::error('Cancel booking error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Verify guest booking with email
     */
    public function verifyGuestBooking($bookingReference)
    {
        try {
            $booking = Booking::where('booking_reference', $bookingReference)
                ->whereNotNull('guest_session_id')
                ->first();
            
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Booking verified',
                'data' => [
                    'booking_reference' => $booking->booking_reference,
                    'holder_email' => $booking->holder_email,
                    'property_name' => $booking->property_name,
                    'check_in_date' => $booking->check_in_date,
                    'check_out_date' => $booking->check_out_date,
                    'total_price' => $booking->total_price,
                    'booking_status' => $booking->booking_status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Verify guest booking error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
}
