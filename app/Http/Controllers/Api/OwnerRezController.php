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
     * @OA\Response(
     *         response=200,
     *         description="Properties retrieved successfully via Channel API",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", 
     *                 type="object",
     *                 @OA\Property(property="items", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="total", type="integer", example=10)
     *             )
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
     * @OA\Response(
     *         response=200,
     *         description="Property details retrieved successfully via Channel API XML",
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
        
        $bookingData = $request->all();
        $propertyId  = $bookingData['propertyId'];
        $guests      = (int)($bookingData['guests'] ?? 2);

        // Step 1: Get a quote to obtain orderItems + paymentSchedule (required by createbooking)
        $quote = $this->ownerRezService->getPricing($propertyId, [
            'checkin'  => $bookingData['checkin'],
            'checkout' => $bookingData['checkout'],
            'guests'   => $guests,
        ]);

        if (!$quote['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pricing quote before booking: ' . ($quote['message'] ?? 'Unknown error'),
            ], 500);
        }

        // Extract orderItems and paymentScheduleItems from quote
        $currency      = $quote['data']['quoteResponseDetails']['orderList']['order']['currency'] ?? 'USD';
        $rawItems      = $quote['data']['quoteResponseDetails']['orderList']['order']['orderItemList']['orderItem'] ?? [];
        // If single item (not array of arrays), wrap it
        if (isset($rawItems['feeType'])) {
            $rawItems = [$rawItems];
        }
        $orderItems = array_map(fn($i) => array_merge($i, ['currency' => $currency]), $rawItems);

        $rawSched = $quote['data']['quoteResponseDetails']['orderList']['order']['paymentSchedule']['paymentScheduleItemList']['paymentScheduleItem'] ?? [];
        if (isset($rawSched['amount'])) {
            $rawSched = [$rawSched];
        }
        $scheduleItems = array_map(fn($s) => [
            'amount'   => $s['amount'],
            'dueDate'  => $s['dueDate'],
            'currency' => $currency,
        ], $rawSched);

        // Step 2: Build the booking payload
        $channelPayload = [
            'listingExternalId'    => $propertyId,
            'unitExternalId'       => $propertyId,
            'arrivalDate'          => $bookingData['checkin'],
            'departureDate'        => $bookingData['checkout'],
            'message'              => $bookingData['specialRequests'] ?? null,
            'traveler'             => [
                'firstName'    => $bookingData['guest']['firstName'],
                'lastName'     => $bookingData['guest']['lastName'],
                'emailAddress' => $bookingData['guest']['email'],
                'phoneNumbers' => [$bookingData['guest']['phone']],
            ],
            'travelers'            => ['adults' => $guests, 'children' => 0, 'pets' => 0],
            'orderItems'           => $orderItems,
            'paymentScheduleItems' => $scheduleItems,
        ];

        $result = $this->ownerRezService->createBooking($channelPayload);

        return response()->json($result, $result['success'] ? 201 : 500);
    }
}
    
