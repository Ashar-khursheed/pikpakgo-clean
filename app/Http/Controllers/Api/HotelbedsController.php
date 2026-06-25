<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HotelbedsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Hotelbeds",
 *     description="Hotel inventory search and booking via Hotelbeds API"
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
     *     description="Search for available hotel rooms via Hotelbeds APITUDE",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"checkIn", "checkOut"},
     *             @OA\Property(property="checkIn", type="string", format="date", example="2027-12-25"),
     *             @OA\Property(property="checkOut", type="string", format="date", example="2027-12-27"),
     *             @OA\Property(property="destinationCode", type="string", example="MIA"),
     *             @OA\Property(property="guests", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hotels retrieved successfully",
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
            'destinationCode' => 'nullable|string|max:10',
            'guests' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0'
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
     *     description="Retrieve detailed static content information about a specific hotel from Hotelbeds",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="hotelCode",
     *         in="path",
     *         required=true,
     *         description="Hotel Code",
     *         @OA\Schema(type="string", example="1001")
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
     *     summary="Check room availability",
     *     description="Check room availability and real-time prices for a specific hotel",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"hotelCode", "checkIn", "checkOut"},
     *             @OA\Property(property="hotelCode", type="string", example="1001"),
     *             @OA\Property(property="checkIn", type="string", format="date", example="2027-12-25"),
     *             @OA\Property(property="checkOut", type="string", format="date", example="2027-12-27"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Availability details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="available", type="boolean", example=true),
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
            'hotelCode' => 'required|string',
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->hotelbedsService->checkAvailability($request->hotelCode, $request->all());
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }
    
    /**
     * @OA\Post(
     *     path="/hotelbeds/bookings",
     *     summary="Create a hotel booking",
     *     description="Book a specific hotel room rateKey via Hotelbeds APITUDE",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rateKey", "traveler"},
     *             @OA\Property(property="rateKey", type="string", example="rate-key-stardust-dbl"),
     *             @OA\Property(
     *                 property="traveler",
     *                 type="object",
     *                 required={"firstName", "lastName"},
     *                 @OA\Property(property="firstName", type="string", example="John"),
     *                 @OA\Property(property="lastName", type="string", example="Doe")
     *             ),
     *             @OA\Property(
     *                 property="guestsList",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="firstName", type="string", example="John"),
     *                     @OA\Property(property="lastName", type="string", example="Doe"),
     *                     @OA\Property(property="type", type="string", example="AD")
     *                 )
     *             ),
     *             @OA\Property(property="clientReference", type="string", example="PKG-1234567")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Hotel booking created successfully",
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
            'rateKey' => 'required|string',
            'traveler' => 'required|array',
            'traveler.firstName' => 'required|string',
            'traveler.lastName' => 'required|string',
            'guestsList' => 'nullable|array',
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
     *     summary="Get hotel booking details",
     *     description="Retrieve details of a booking reservation made via Hotelbeds",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="bookingReference",
     *         in="path",
     *         required=true,
     *         description="Hotelbeds Booking Reference",
     *         @OA\Schema(type="string", example="HB-123456789")
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
     *     summary="Cancel hotel booking",
     *     description="Cancel a confirmed booking reservation on Hotelbeds",
     *     tags={"Hotelbeds"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="bookingReference",
     *         in="path",
     *         required=true,
     *         description="Hotelbeds Booking Reference",
     *         @OA\Schema(type="string", example="HB-123456789")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Booking cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Failed to cancel booking"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function cancelBooking($bookingReference)
    {
        $result = $this->hotelbedsService->cancelBooking($bookingReference);
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
