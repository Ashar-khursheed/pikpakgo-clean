<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BookingCancellationMail;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\GuestSession;
use App\Models\PropertyFee;
use App\Models\PropertyListing;
use App\Services\PricingMarkupService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
    protected $pricingService;

    public function __construct(PricingMarkupService $pricingService)
    {
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
                'payment_status' => 'pending',
                // Provider submission happens AFTER payment is captured
                'provider_payout_status' => 'pending',
            ]);

            // Apply property fees
            $feeData = $this->calculatePropertyFees(
                $request->property_code,
                $request->base_price,
                $nights,
                ($request->total_adults ?? 1) + ($request->total_children ?? 0)
            );
            $booking->update([
                'cleaning_fee' => $feeData['cleaning_fee'],
                'service_fee' => $feeData['service_fee'],
                'damage_deposit' => $feeData['damage_deposit'],
                'other_fees' => $feeData['other_fees'],
                'fees_breakdown' => $feeData['fees_breakdown'],
                'total_price' => $markupData['final_price'] + $feeData['total_fees'],
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

            // Send confirmation email
            try {
                Mail::to($booking->holder_email)->queue(new BookingConfirmationMail($booking->fresh()));
            } catch (\Exception $e) {
                Log::warning('Failed to send guest booking confirmation email: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'booking' => $booking->fresh(),
                    'booking_reference' => $bookingReference,
                    'fees_breakdown' => $feeData['fees_breakdown'],
                    'confirmation_email_sent' => true
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
                'payment_status' => 'pending',
                // Provider submission happens AFTER payment is captured
                'provider_payout_status' => 'pending',
            ]);

            // Apply property fees
            $feeData = $this->calculatePropertyFees(
                $request->property_code,
                $request->base_price,
                $nights,
                ($request->total_adults ?? 1) + ($request->total_children ?? 0)
            );
            $booking->update([
                'cleaning_fee' => $feeData['cleaning_fee'],
                'service_fee' => $feeData['service_fee'],
                'damage_deposit' => $feeData['damage_deposit'],
                'other_fees' => $feeData['other_fees'],
                'fees_breakdown' => $feeData['fees_breakdown'],
                'total_price' => $markupData['final_price'] + $feeData['total_fees'],
            ]);

            // Send confirmation email
            try {
                Mail::to($user->email)->queue(new BookingConfirmationMail($booking->fresh()));
            } catch (\Exception $e) {
                Log::warning('Failed to send booking confirmation email: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'booking' => $booking->fresh(),
                    'booking_reference' => $bookingReference,
                    'fees_breakdown' => $feeData['fees_breakdown'],
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

            // Rollback reward points
            try {
                app(\App\Services\RewardService::class)->rollbackPointsForBooking($booking);
            } catch (\Exception $e) {
                Log::error('Failed to rollback reward points: ' . $e->getMessage());
            }

            $refundAmount = $booking->is_refundable ? (float) $booking->paid_amount : 0;
            try {
                Mail::to($booking->holder_email)->queue(new BookingCancellationMail($booking->fresh(), $refundAmount));
            } catch (\Exception $e) {
                Log::warning('Failed to send guest cancellation email: ' . $e->getMessage());
            }

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

            // Rollback reward points
            try {
                app(\App\Services\RewardService::class)->rollbackPointsForBooking($booking);
            } catch (\Exception $e) {
                Log::error('Failed to rollback reward points: ' . $e->getMessage());
            }

            $refundAmount = $booking->is_refundable ? (float) $booking->paid_amount : 0;
            try {
                Mail::to($booking->holder_email)->queue(new BookingCancellationMail($booking->fresh(), $refundAmount));
            } catch (\Exception $e) {
                Log::warning('Failed to send cancellation email: ' . $e->getMessage());
            }

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

    /**
     * Calculate fees for a property listing
     */
    private function calculatePropertyFees(string $propertyCode, float $basePrice, int $nights, int $guests): array
    {
        $listing = PropertyListing::where('provider_property_id', $propertyCode)->first();
        if (!$listing) {
            return ['cleaning_fee' => 0, 'service_fee' => 0, 'damage_deposit' => 0, 'other_fees' => [], 'fees_breakdown' => [], 'total_fees' => 0];
        }

        $fees = PropertyFee::where('property_listing_id', $listing->id)->active()->get();

        $cleaningFee = 0;
        $serviceFee = 0;
        $damageDeposit = 0;
        $otherFees = [];
        $breakdown = [];

        foreach ($fees as $fee) {
            $amount = $fee->calculate($basePrice, $nights, $guests);
            $breakdown[] = [
                'fee_type' => $fee->fee_type,
                'fee_name' => $fee->fee_name,
                'amount' => $amount,
                'amount_type' => $fee->amount_type,
                'applies_to' => $fee->applies_to,
                'is_mandatory' => $fee->is_mandatory,
            ];

            match ($fee->fee_type) {
                'cleaning_fee' => $cleaningFee += $amount,
                'service_fee' => $serviceFee += $amount,
                'damage_deposit' => $damageDeposit += $amount,
                default => $otherFees[] = ['name' => $fee->fee_name, 'amount' => $amount, 'type' => $fee->fee_type],
            };
        }

        $otherTotal = array_sum(array_column($otherFees, 'amount'));

        return [
            'cleaning_fee' => $cleaningFee,
            'service_fee' => $serviceFee,
            'damage_deposit' => $damageDeposit,
            'other_fees' => $otherFees,
            'fees_breakdown' => $breakdown,
            'total_fees' => $cleaningFee + $serviceFee + $otherTotal,
        ];
    }

    /**
     * @OA\Get(
     *     path="/bookings/{bookingReference}/invoice",
     *     summary="Download booking invoice as PDF",
     *     tags={"Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="bookingReference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="PDF invoice file"),
     *     @OA\Response(response=403, description="Forbidden — not your booking"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function downloadInvoice($bookingReference)
    {
        $booking = Booking::where('booking_reference', $bookingReference)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $pdf = Pdf::loadView('invoices.booking', ['booking' => $booking])
            ->setPaper('a4', 'portrait');

        return $pdf->download('invoice-' . $booking->booking_reference . '.pdf');
    }
}
