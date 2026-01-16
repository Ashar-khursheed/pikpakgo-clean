<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HotelbedsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Hotelbeds",
 *     description="Hotel booking and management via Hotelbeds API"
 * )
 */
class HotelbedsController extends Controller
{
    protected $hotelbedsService;
    
    public function __construct(HotelbedsService $hotelbedsService)
    {
        $this->hotelbedsService = $hotelbedsService;
    }
    
    /**
     * @OA\Post(
     *     path="/hotelbeds/search",
     *     summary="Search hotels",
     *     description="Search for available hotels based on destination, dates, and occupancy",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"checkIn", "checkOut", "destinationCode"},
     *             @OA\Property(property="checkIn", type="string", format="date", example="2024-12-25", description="Check-in date (YYYY-MM-DD)"),
     *             @OA\Property(property="checkOut", type="string", format="date", example="2024-12-27", description="Check-out date (YYYY-MM-DD)"),
     *             @OA\Property(property="destinationCode", type="string", example="NYC", description="Destination code"),
     *             @OA\Property(
     *                 property="occupancies",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="rooms", type="integer", example=1),
     *                     @OA\Property(property="adults", type="integer", example=2),
     *                     @OA\Property(property="children", type="integer", example=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful search",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function searchHotels(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'destinationCode' => 'required|string',
            'occupancies' => 'nullable|array',
            'occupancies.*.rooms' => 'integer|min:1',
            'occupancies.*.adults' => 'integer|min:1',
            'occupancies.*.children' => 'integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->hotelbedsService->searchHotels($request->all());
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }
    
    /**
     * @OA\Get(
     *     path="/hotelbeds/hotels/{hotelCode}",
     *     summary="Get hotel details",
     *     description="Retrieve detailed information about a specific hotel",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="hotelCode",
     *         in="path",
     *         required=true,
     *         description="Hotel code",
     *         @OA\Schema(type="string", example="12345")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hotel details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Hotel not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getHotelDetails($hotelCode)
    {
        $result = $this->hotelbedsService->getHotelDetails($hotelCode);
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }
    
    /**
     * @OA\Post(
     *     path="/hotelbeds/check-availability",
     *     summary="Check hotel availability",
     *     description="Check availability and rates for selected rooms",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rooms"},
     *             @OA\Property(property="rooms", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="upselling", type="boolean", example=false)
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
    public function checkAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rooms' => 'required|array',
            'upselling' => 'nullable|boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->hotelbedsService->checkAvailability($request->all());
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }
    
    /**
     * @OA\Post(
     *     path="/hotelbeds/bookings",
     *     summary="Create hotel booking",
     *     description="Create a new hotel booking reservation",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"holder", "rooms"},
     *             @OA\Property(
     *                 property="holder",
     *                 type="object",
     *                 @OA\Property(property="name", type="string", example="John"),
     *                 @OA\Property(property="surname", type="string", example="Doe")
     *             ),
     *             @OA\Property(property="rooms", type="array", @OA\Items(type="object")),
     *             @OA\Property(
     *                 property="clientReference",
     *                 type="string",
     *                 example="CLIENT-REF-12345"
     *             )
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
            'holder' => 'required|array',
            'holder.name' => 'required|string',
            'holder.surname' => 'required|string',
            'rooms' => 'required|array',
            'clientReference' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->hotelbedsService->createBooking($request->all());
        
        return response()->json($result, $result['success'] ? 201 : 500);
    }
    
    /**
     * @OA\Get(
     *     path="/hotelbeds/bookings/{bookingReference}",
     *     summary="Get booking details",
     *     description="Retrieve details of a specific booking",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="bookingReference",
     *         in="path",
     *         required=true,
     *         description="Booking reference number",
     *         @OA\Schema(type="string", example="1-12345")
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
    public function getBooking($bookingReference)
    {
        $result = $this->hotelbedsService->getBooking($bookingReference);
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }
    
    /**
     * @OA\Delete(
     *     path="/hotelbeds/bookings/{bookingReference}",
     *     summary="Cancel booking",
     *     description="Cancel an existing hotel booking",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="bookingReference",
     *         in="path",
     *         required=true,
     *         description="Booking reference number",
     *         @OA\Schema(type="string", example="1-12345")
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
    public function cancelBooking($bookingReference)
    {
        $result = $this->hotelbedsService->cancelBooking($bookingReference);
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }
}
