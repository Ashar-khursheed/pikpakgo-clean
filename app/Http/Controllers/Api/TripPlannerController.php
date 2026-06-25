<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Models\Booking;
use App\Models\FlightBooking;
use App\Models\CarBooking;
use App\Models\ExperienceBooking;
use App\Models\TransferBooking;
use App\Services\TripPlannerService;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="AI Trip Planner",
 *     description="AI Itinerary generation, Smart Cart additions, and unified checkout"
 * )
 */
class TripPlannerController extends Controller
{
    protected $plannerService;

    public function __construct(TripPlannerService $plannerService)
    {
        $this->plannerService = $plannerService;
    }

    /**
     * @OA\Post(
     *     path="/trip-planner/generate",
     *     summary="Generate AI travel itinerary & recommendations",
     *     tags={"AI Trip Planner"},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"destination", "interests"},
     *         @OA\Property(property="destination", type="string", example="Miami"),
     *         @OA\Property(property="start_date", type="string", format="date", example="2027-01-23"),
     *         @OA\Property(property="end_date", type="string", format="date", example="2027-01-26"),
     *         @OA\Property(property="interests", type="array", @OA\Items(type="string"), example={"beach", "nightlife", "seafood"})
     *     )),
     *     @OA\Response(response=200, description="Itinerary generated successfully")
     * )
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'destination' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'interests' => 'required|array|min:1',
            'interests.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $plan = $this->plannerService->generateItinerary(
                $request->destination,
                $request->start_date,
                $request->end_date,
                $request->interests
            );

            $recommendations = $this->plannerService->getRecommendedListings(
                $request->destination,
                $request->interests
            );

            return response()->json([
                'success' => true,
                'itinerary' => $plan,
                'recommended_properties' => $recommendations
            ]);

        } catch (\Exception $e) {
            Log::error('Itinerary generation controller error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to generate itinerary.'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/trip-planner/itineraries",
     *     summary="Save a planned itinerary (Smart Cart container)",
     *     tags={"AI Trip Planner"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"title", "destination"},
     *         @OA\Property(property="title", type="string", example="My Miami Getaway"),
     *         @OA\Property(property="destination", type="string", example="Miami"),
     *         @OA\Property(property="start_date", type="string", format="date", example="2027-01-23"),
     *         @OA\Property(property="end_date", type="string", format="date", example="2027-01-26"),
     *         @OA\Property(property="interests", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="ai_recommendations", type="object")
     *     )),
     *     @OA\Response(response=201, description="Itinerary saved")
     * )
     */
    public function saveItinerary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'interests' => 'nullable|array',
            'ai_recommendations' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $itinerary = Itinerary::create([
                'user_id' => auth()->id(),
                'title' => $request->title,
                'destination' => $request->destination,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'interests' => $request->interests,
                'ai_recommendations' => $request->ai_recommendations,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Itinerary saved successfully.',
                'data' => $itinerary
            ], 201);

        } catch (\Exception $e) {
            Log::error('Save itinerary error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save itinerary.'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/trip-planner/itineraries",
     *     summary="List all itineraries for current user",
     *     tags={"AI Trip Planner"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of itineraries")
     * )
     */
    public function listItineraries()
    {
        $itineraries = Itinerary::where('user_id', auth()->id())
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $itineraries
        ]);
    }

    /**
     * @OA\Get(
     *     path="/trip-planner/itineraries/{id}",
     *     summary="Get details of a specific itinerary along with smart cart items",
     *     tags={"AI Trip Planner"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Itinerary details with items")
     * )
     */
    public function showItinerary($id)
    {
        try {
            $itinerary = Itinerary::where('id', $id)
                ->where('user_id', auth()->id())
                ->with('items')
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $itinerary
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Itinerary not found.'], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/trip-planner/itineraries/{id}/add-item",
     *     summary="Add an item to the itinerary Smart Cart",
     *     tags={"AI Trip Planner"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"item_type", "item_id", "item_details", "price"},
     *         @OA\Property(property="item_type", type="string", enum={"hotel", "flight", "car", "experience", "transfer"}),
     *         @OA\Property(property="item_id", type="string", example="FL-241"),
     *         @OA\Property(property="item_details", type="object"),
     *         @OA\Property(property="price", type="number", example=250.00)
     *     )),
     *     @OA\Response(response=201, description="Item added successfully")
     * )
     */
    public function addItem(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'item_type' => 'required|in:hotel,flight,car,experience,transfer',
            'item_id' => 'required|string',
            'item_details' => 'required|array',
            'price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $itinerary = Itinerary::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $item = ItineraryItem::create([
                'itinerary_id' => $itinerary->id,
                'item_type' => $request->item_type,
                'item_id' => $request->item_id,
                'item_details' => $request->item_details,
                'price' => $request->price,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item added to smart cart successfully.',
                'data' => $item
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to add item to itinerary.'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/trip-planner/itineraries/{id}/checkout",
     *     summary="Unified checkout of all smart cart items in the itinerary",
     *     tags={"AI Trip Planner"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"payment_method"},
     *         @OA\Property(property="payment_method", type="string", enum={"credit_card", "stripe_checkout"}),
     *         @OA\Property(property="card_number", type="string"),
     *         @OA\Property(property="card_holder_name", type="string"),
     *         @OA\Property(property="card_expiry_month", type="string"),
     *         @OA\Property(property="card_expiry_year", type="string"),
     *         @OA\Property(property="card_cvv", type="string")
     *     )),
     *     @OA\Response(response=200, description="Unified checkout successful")
     * )
     */
    public function checkout(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $itinerary = Itinerary::where('id', $id)
                ->where('user_id', $user->id)
                ->with('items')
                ->firstOrFail();

            if ($itinerary->items->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Smart cart is empty.'], 400);
            }

            $totalPrice = $itinerary->items->sum('price');
            $ref = 'PKG-UNI-' . strtoupper(Str::random(8));

            // Simulating payment transaction for total cart price
            $transactionId = 'TXN-' . strtoupper(Str::random(16));
            $transaction = PaymentTransaction::create([
                'transaction_id' => $transactionId,
                'user_id' => $user->id,
                'payment_gateway' => $request->payment_method === 'stripe_checkout' ? 'stripe' : 'authorize_net',
                'amount' => $totalPrice,
                'currency' => 'USD',
                'transaction_type' => 'payment',
                'payment_method' => $request->payment_method,
                'card_brand' => 'Visa',
                'card_last_four' => '1111',
                'card_holder_name' => $request->card_holder_name ?? $user->first_name . ' ' . $user->last_name,
                'billing_email' => $user->email,
                'status' => 'success',
                'processed_at' => now(),
            ]);

            // Save individual bookings for each item in the cart!
            $bookingsCreated = [];

            foreach ($itinerary->items as $item) {
                $details = $item->item_details;
                $itemRef = strtoupper($item->item_type[0] . $item->item_type[1]) . '-' . strtoupper(Str::random(8));

                switch ($item->item_type) {
                    case 'hotel':
                        $bookingsCreated[] = Booking::create([
                            'booking_reference' => $itemRef,
                            'provider' => 'hotelbeds',
                            'user_id' => $user->id,
                            'holder_first_name' => $user->first_name,
                            'holder_last_name' => $user->last_name,
                            'holder_email' => $user->email,
                            'holder_phone' => $user->phone ?? '0000000000',
                            'property_code' => $item->item_id,
                            'property_name' => $details['name'] ?? 'Hotel Listing',
                            'check_in_date' => $details['check_in'] ?? now()->addDays(5)->toDateString(),
                            'check_out_date' => $details['check_out'] ?? now()->addDays(7)->toDateString(),
                            'nights' => 2,
                            'total_adults' => 2,
                            'base_price' => $item->price * 0.9,
                            'markup_amount' => $item->price * 0.1,
                            'total_price' => $item->price,
                            'payment_status' => 'paid',
                            'booking_status' => 'confirmed',
                            'payment_transaction_id' => $transactionId,
                            'paid_amount' => $item->price,
                            'paid_at' => now(),
                        ]);
                        break;

                    case 'flight':
                        $bookingsCreated[] = FlightBooking::create([
                            'user_id' => $user->id,
                            'booking_reference' => $itemRef,
                            'airline' => $details['airline'] ?? 'Airline',
                            'flight_number' => $details['flight_number'] ?? 'FL-100',
                            'departure_airport' => $details['departure_airport'] ?? 'JFK',
                            'arrival_airport' => $details['arrival_airport'] ?? 'LAX',
                            'departure_time' => $details['departure_time'] ?? now()->addDays(5),
                            'arrival_time' => $details['arrival_time'] ?? now()->addDays(5)->addHours(6),
                            'passenger_details' => [['first_name' => $user->first_name, 'last_name' => $user->last_name]],
                            'total_price' => $item->price,
                            'status' => 'confirmed',
                        ]);
                        break;

                    case 'car':
                        $bookingsCreated[] = CarBooking::create([
                            'user_id' => $user->id,
                            'booking_reference' => $itemRef,
                            'rental_company' => $details['rental_company'] ?? 'RentalCar',
                            'car_model' => $details['car_model'] ?? 'Economy Car',
                            'car_class' => $details['car_class'] ?? 'Economy',
                            'pickup_location' => $details['pickup_location'] ?? 'Airport',
                            'dropoff_location' => $details['dropoff_location'] ?? 'Airport',
                            'pickup_time' => $details['pickup_time'] ?? now()->addDays(5),
                            'dropoff_time' => $details['dropoff_time'] ?? now()->addDays(7),
                            'driver_details' => ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'license_number' => 'DL123456'],
                            'total_price' => $item->price,
                            'status' => 'confirmed',
                        ]);
                        break;

                    case 'experience':
                        $bookingsCreated[] = ExperienceBooking::create([
                            'user_id' => $user->id,
                            'booking_reference' => $itemRef,
                            'experience_name' => $details['experience_name'] ?? 'Experience Tour',
                            'category' => $details['category'] ?? 'experience',
                            'activity_date' => $details['activity_date'] ?? now()->addDays(6),
                            'quantity' => $details['quantity'] ?? 1,
                            'ticket_details' => [['name' => $user->first_name . ' ' . $user->last_name, 'type' => 'Adult']],
                            'total_price' => $item->price,
                            'status' => 'confirmed',
                        ]);
                        break;

                    case 'transfer':
                        $bookingsCreated[] = TransferBooking::create([
                            'user_id' => $user->id,
                            'booking_reference' => $itemRef,
                            'pickup_location' => $details['pickup_location'] ?? 'Airport',
                            'dropoff_location' => $details['dropoff_location'] ?? 'Hotel',
                            'transfer_time' => $details['transfer_time'] ?? now()->addDays(5),
                            'transfer_type' => $details['transfer_type'] ?? 'private_sedan',
                            'passenger_count' => $details['passenger_count'] ?? 2,
                            'total_price' => $item->price,
                            'status' => 'confirmed',
                        ]);
                        break;
                }
            }

            // Empty the smart cart after checkout
            $itinerary->items()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Smart cart unified checkout completed successfully.',
                'transaction_id' => $transactionId,
                'total_charged' => $totalPrice,
                'bookings_created' => count($bookingsCreated),
            ]);

        } catch (\Exception $e) {
            Log::error('Itinerary checkout error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Checkout failed.'], 500);
        }
    }
}
