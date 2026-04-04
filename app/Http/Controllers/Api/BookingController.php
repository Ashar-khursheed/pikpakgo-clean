<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\GuestSession;
use App\Services\OwnerRezService;
use App\Services\PricingMarkupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


/**
 * @OA\Tag(
 *     name="Bookings",
 *     description="Booking management — guest and authenticated user flows"
 * )
 */
class BookingController extends Controller
{
    protected $ownerrezService;
    protected $pricingService;
    
    public function __construct(
        OwnerRezService $ownerrezService,
        PricingMarkupService $pricingService
    ) {
        $this->ownerrezService = $ownerrezService;
        $this->pricingService = $pricingService;
    }
    
    /**
     * @OA\Post(
     *     path="/bookings/guest/create",
     *     summary="Create guest booking",
     *     tags={"Bookings"},
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     *     @OA\Response(response=201, description="Booking created")
     * )
     */
    public function createGuestBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_session_id' => 'required|string',
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
                'provider' => 'ownerrez',
                'property_type' => $request->property_type ?? 'vacation_rental',
                'destination_code' => $request->destination_code,
                'check_in_date' => $request->check_in_date,
            ]);
            
            // Generate unique booking reference
            $bookingReference = 'PKG-' . strtoupper(Str::random(10));
            
            // Create booking in database
            $booking = Booking::create([
                'booking_reference' => $bookingReference,
                'provider' => 'ownerrez',
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
            
            // Fetch quote to obtain orderItems + paymentSchedule (required by OwnerRez createbooking)
            $quote = $this->ownerrezService->getPricing($request->property_code, [
                'checkin'  => $request->check_in_date,
                'checkout' => $request->check_out_date,
                'guests'   => $request->total_adults ?? 2,
            ]);

            if (!$quote['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get pricing quote: ' . ($quote['message'] ?? 'Unknown error'),
                ], 500);
            }

            $currency  = $quote['data']['quoteResponseDetails']['orderList']['order']['currency'] ?? ($request->currency ?? 'USD');
            $rawItems  = $quote['data']['quoteResponseDetails']['orderList']['order']['orderItemList']['orderItem'] ?? [];
            if (isset($rawItems['feeType'])) $rawItems = [$rawItems];
            $orderItems = array_map(fn($i) => array_merge($i, ['currency' => $currency]), $rawItems);

            $rawSched = $quote['data']['quoteResponseDetails']['orderList']['order']['paymentSchedule']['paymentScheduleItemList']['paymentScheduleItem'] ?? [];
            if (isset($rawSched['amount'])) $rawSched = [$rawSched];
            $scheduleItems = array_map(fn($s) => [
                'amount'   => $s['amount'],
                'dueDate'  => $s['dueDate'],
                'currency' => $currency,
            ], $rawSched);

            // Call API
            $channelPayload = [
                'listingExternalId'    => $request->property_code,
                'unitExternalId'       => $request->property_code,
                'arrivalDate'          => $request->check_in_date,
                'departureDate'        => $request->check_out_date,
                'traveler'             => [
                    'firstName'    => $request->holder_first_name,
                    'lastName'     => $request->holder_last_name,
                    'emailAddress' => $request->holder_email,
                    'phoneNumbers' => [$request->holder_phone],
                ],
                'travelers'            => ['adults' => (int)($request->total_adults ?? 2), 'children' => 0, 'pets' => 0],
                'orderItems'           => $orderItems,
                'paymentScheduleItems' => $scheduleItems,
            ];

            $apiResult = $this->ownerrezService->createBooking($channelPayload);
            
            if (!$apiResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to confirm booking with OwnerRez',
                    'error' => $apiResult['error'] ?? null
                ], 500);
            }
            
            // Save the external ID from OwnerRez to our local booking reference
            if (isset($apiResult['data']['externalId'])) {
                $booking->update([
                    'provider_booking_id' => $apiResult['data']['externalId']
                ]);
            }
            
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
                    'booking' => $booking->fresh(),
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
     * @OA\Post(
     *     path="/bookings",
     *     summary="Create authenticated user booking",
     *     tags={"Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"property_code","check_in_date","check_out_date","total_adults","total_rooms","property_name","base_price"},
     *             @OA\Property(property="property_code", type="string", example="orp5b27f9x"),
     *             @OA\Property(property="check_in_date", type="string", format="date", example="2027-01-23"),
     *             @OA\Property(property="check_out_date", type="string", format="date", example="2027-01-26"),
     *             @OA\Property(property="total_adults", type="integer", example=2),
     *             @OA\Property(property="total_rooms", type="integer", example=1),
     *             @OA\Property(property="property_name", type="string", example="Beach House"),
     *             @OA\Property(property="base_price", type="number", example=500.00),
     *             @OA\Property(property="provider", type="string", example="ownerrez"),
     *             @OA\Property(property="special_requests", type="string", example="Late check-in")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Booking created successfully"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function createBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
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
            
            // Call Provider API if ownerrez
            if ($request->provider === 'ownerrez') {
                // Fetch quote to obtain orderItems + paymentSchedule (required by OwnerRez createbooking)
                $quote = $this->ownerrezService->getPricing($request->property_code, [
                    'checkin'  => $request->check_in_date,
                    'checkout' => $request->check_out_date,
                    'guests'   => $request->total_adults ?? 2,
                ]);

                if (!$quote['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to get pricing quote: ' . ($quote['message'] ?? 'Unknown error'),
                    ], 500);
                }

                $currency  = $quote['data']['quoteResponseDetails']['orderList']['order']['currency'] ?? ($request->currency ?? 'USD');
                $rawItems  = $quote['data']['quoteResponseDetails']['orderList']['order']['orderItemList']['orderItem'] ?? [];
                if (isset($rawItems['feeType'])) $rawItems = [$rawItems];
                $orderItems = array_map(fn($i) => array_merge($i, ['currency' => $currency]), $rawItems);

                $rawSched = $quote['data']['quoteResponseDetails']['orderList']['order']['paymentSchedule']['paymentScheduleItemList']['paymentScheduleItem'] ?? [];
                if (isset($rawSched['amount'])) $rawSched = [$rawSched];
                $scheduleItems = array_map(fn($s) => [
                    'amount'   => $s['amount'],
                    'dueDate'  => $s['dueDate'],
                    'currency' => $currency,
                ], $rawSched);

                $channelPayload = [
                    'listingExternalId'    => $request->property_code,
                    'unitExternalId'       => $request->property_code,
                    'arrivalDate'          => $request->check_in_date,
                    'departureDate'        => $request->check_out_date,
                    'traveler'             => [
                        'firstName'    => $user->first_name,
                        'lastName'     => $user->last_name,
                        'emailAddress' => $user->email,
                        'phoneNumbers' => [$user->phone ?? $request->holder_phone],
                    ],
                    'travelers'            => ['adults' => (int)($request->total_adults ?? 2), 'children' => 0, 'pets' => 0],
                    'orderItems'           => $orderItems,
                    'paymentScheduleItems' => $scheduleItems,
                ];

                $apiResult = $this->ownerrezService->createBooking($channelPayload);
                
                if (!$apiResult['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to confirm booking with OwnerRez',
                        'error' => $apiResult['error'] ?? null
                    ], 500);
                }

                // Save the external ID from OwnerRez to our local booking reference
                if (isset($apiResult['data']['externalId'])) {
                    $booking->update([
                        'provider_booking_id' => $apiResult['data']['externalId']
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'booking' => $booking->fresh(),
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
     * @OA\Get(
     *     path="/bookings/guest/{bookingReference}",
     *     summary="Get guest booking by reference",
     *     tags={"Bookings"},
     *     @OA\Parameter(name="bookingReference", in="path", required=true, @OA\Schema(type="string", example="PKG-ABCD1234XY")),
     *     @OA\Response(response=200, description="Booking found"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
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
     * @OA\Get(
     *     path="/bookings",
     *     summary="Get all bookings for authenticated user",
     *     tags={"Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", example="confirmed")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=10)),
     *     @OA\Response(response=200, description="Paginated list of bookings"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
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
     * @OA\Get(
     *     path="/bookings/{bookingReference}",
     *     summary="Get single booking for authenticated user",
     *     tags={"Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="bookingReference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Booking details"),
     *     @OA\Response(response=404, description="Booking not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
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
     * @OA\Post(
     *     path="/bookings/guest/{bookingReference}/cancel",
     *     summary="Cancel a guest booking",
     *     tags={"Bookings"},
     *     @OA\Parameter(name="bookingReference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"email"},
     *         @OA\Property(property="email", type="string", format="email"),
     *         @OA\Property(property="reason", type="string")
     *     )),
     *     @OA\Response(response=200, description="Booking cancelled"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
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
     * @OA\Post(
     *     path="/bookings/{bookingReference}/cancel",
     *     summary="Cancel an authenticated user booking",
     *     tags={"Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="bookingReference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="reason", type="string"))),
     *     @OA\Response(response=200, description="Booking cancelled"),
     *     @OA\Response(response=404, description="Booking not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
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
     * @OA\Get(
     *     path="/bookings/guest/{bookingReference}/verify",
     *     summary="Verify a guest booking",
     *     tags={"Bookings"},
     *     @OA\Parameter(name="bookingReference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Booking verified"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
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
