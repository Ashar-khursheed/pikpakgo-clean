<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OwnerRezService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="OwnerRez",
 *     description="Vacation rental property search and booking via OwnerRez API"
 * )
 */
class OwnerRezController extends Controller
{
    protected $ownerRezService;
    
    public function __construct(OwnerRezService $ownerRezService)
    {
        $this->ownerRezService = $ownerRezService;
    }
    
    /**
     * @OA\Get(
     *     path="/ownerrez/properties",
     *     summary="Search vacation rental properties",
     *     description="Search for available vacation rental properties",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="location",
     *         in="query",
     *         description="Location or city name",
     *         @OA\Schema(type="string", example="Miami Beach")
     *     ),
     *     @OA\Parameter(
     *         name="checkin",
     *         in="query",
     *         description="Check-in date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2024-12-25")
     *     ),
     *     @OA\Parameter(
     *         name="checkout",
     *         in="query",
     *         description="Check-out date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2024-12-27")
     *     ),
     *     @OA\Parameter(
     *         name="guests",
     *         in="query",
     *         description="Number of guests",
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="bedrooms",
     *         in="query",
     *         description="Minimum number of bedrooms",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="bathrooms",
     *         in="query",
     *         description="Minimum number of bathrooms",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="minPrice",
     *         in="query",
     *         description="Minimum price per night",
     *         @OA\Schema(type="number", example=100)
     *     ),
     *     @OA\Parameter(
     *         name="maxPrice",
     *         in="query",
     *         description="Maximum price per night",
     *         @OA\Schema(type="number", example=500)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Properties retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function searchProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'nullable|string',
            'checkin' => 'nullable|date|after_or_equal:today',
            'checkout' => 'nullable|date|after:checkin',
            'guests' => 'nullable|integer|min:1',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'minPrice' => 'nullable|numeric|min:0',
            'maxPrice' => 'nullable|numeric|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->ownerRezService->searchProperties($request->all());
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }
    
    /**
     * @OA\Get(
     *     path="/ownerrez/properties/{propertyId}",
     *     summary="Get property details",
     *     description="Retrieve detailed information about a specific property",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="propertyId",
     *         in="path",
     *         required=true,
     *         description="Property ID",
     *         @OA\Schema(type="string", example="PROP-12345")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Property details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Property not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getPropertyDetails($propertyId)
    {
        $result = $this->ownerRezService->getPropertyDetails($propertyId);
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }
    
    /**
     * @OA\Post(
     *     path="/ownerrez/properties/{propertyId}/availability",
     *     summary="Check property availability",
     *     description="Check if a property is available for specific dates",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="propertyId",
     *         in="path",
     *         required=true,
     *         description="Property ID",
     *         @OA\Schema(type="string", example="PROP-12345")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"checkin", "checkout"},
     *             @OA\Property(property="checkin", type="string", format="date", example="2024-12-25"),
     *             @OA\Property(property="checkout", type="string", format="date", example="2024-12-27"),
     *             @OA\Property(property="guests", type="integer", example=4)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Availability checked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function checkAvailability(Request $request, $propertyId)
    {
        $validator = Validator::make($request->all(), [
            'checkin' => 'required|date|after_or_equal:today',
            'checkout' => 'required|date|after:checkin',
            'guests' => 'nullable|integer|min:1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->ownerRezService->checkAvailability($propertyId, $request->all());
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }
    
    /**
     * @OA\Post(
     *     path="/ownerrez/properties/{propertyId}/pricing",
     *     summary="Get property pricing",
     *     description="Get pricing information for a property",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="propertyId",
     *         in="path",
     *         required=true,
     *         description="Property ID",
     *         @OA\Schema(type="string", example="PROP-12345")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"checkin", "checkout"},
     *             @OA\Property(property="checkin", type="string", format="date", example="2024-12-25"),
     *             @OA\Property(property="checkout", type="string", format="date", example="2024-12-27"),
     *             @OA\Property(property="guests", type="integer", example=4)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pricing retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getPricing(Request $request, $propertyId)
    {
        $validator = Validator::make($request->all(), [
            'checkin' => 'required|date|after_or_equal:today',
            'checkout' => 'required|date|after:checkin',
            'guests' => 'nullable|integer|min:1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->ownerRezService->getPricing($propertyId, $request->all());
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }
    
    /**
     * @OA\Post(
     *     path="/ownerrez/bookings",
     *     summary="Create a booking",
     *     description="Create a new booking reservation for a property",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"propertyId", "checkin", "checkout", "guest"},
     *             @OA\Property(property="propertyId", type="string", example="PROP-12345"),
     *             @OA\Property(property="checkin", type="string", format="date", example="2024-12-25"),
     *             @OA\Property(property="checkout", type="string", format="date", example="2024-12-27"),
     *             @OA\Property(
     *                 property="guest",
     *                 type="object",
     *                 @OA\Property(property="firstName", type="string", example="John"),
     *                 @OA\Property(property="lastName", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890")
     *             ),
     *             @OA\Property(property="guests", type="integer", example=4),
     *             @OA\Property(property="specialRequests", type="string", example="Late check-in")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Booking created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function createBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propertyId' => 'required|string',
            'checkin' => 'required|date|after_or_equal:today',
            'checkout' => 'required|date|after:checkin',
            'guest' => 'required|array',
            'guest.firstName' => 'required|string',
            'guest.lastName' => 'required|string',
            'guest.email' => 'required|email',
            'guest.phone' => 'required|string',
            'guests' => 'nullable|integer|min:1',
            'specialRequests' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->ownerRezService->createBooking($request->all());
        
        return response()->json($result, $result['success'] ? 201 : 500);
    }
    
    /**
     * @OA\Get(
     *     path="/ownerrez/bookings/{bookingId}",
     *     summary="Get booking details",
     *     description="Retrieve details of a specific booking",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="bookingId",
     *         in="path",
     *         required=true,
     *         description="Booking ID",
     *         @OA\Schema(type="string", example="BOOK-12345")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Booking details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Booking not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getBooking($bookingId)
    {
        $result = $this->ownerRezService->getBooking($bookingId);
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }
    
    /**
     * @OA\Put(
     *     path="/ownerrez/bookings/{bookingId}",
     *     summary="Update booking",
     *     description="Update an existing booking",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="bookingId",
     *         in="path",
     *         required=true,
     *         description="Booking ID",
     *         @OA\Schema(type="string", example="BOOK-12345")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="guests", type="integer", example=5),
     *             @OA\Property(property="specialRequests", type="string", example="Early check-in")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Booking updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Booking not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateBooking(Request $request, $bookingId)
    {
        $result = $this->ownerRezService->updateBooking($bookingId, $request->all());
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }
    
    /**
     * @OA\Delete(
     *     path="/ownerrez/bookings/{bookingId}",
     *     summary="Cancel booking",
     *     description="Cancel an existing booking",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="bookingId",
     *         in="path",
     *         required=true,
     *         description="Booking ID",
     *         @OA\Schema(type="string", example="BOOK-12345")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Booking cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Booking not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function cancelBooking($bookingId)
    {
        $result = $this->ownerRezService->cancelBooking($bookingId);
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }
    
    /**
     * @OA\Get(
     *     path="/ownerrez/properties/{propertyId}/reviews",
     *     summary="Get property reviews",
     *     description="Retrieve reviews for a specific property",
     *     tags={"OwnerRez"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="propertyId",
     *         in="path",
     *         required=true,
     *         description="Property ID",
     *         @OA\Schema(type="string", example="PROP-12345")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Property not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getReviews($propertyId)
    {
        $result = $this->ownerRezService->getReviews($propertyId);
        
        return response()->json($result, $result['success'] ? 200 : 200);
    }
}
