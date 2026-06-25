<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlightBooking;
use App\Models\CarBooking;
use App\Models\ExperienceBooking;
use App\Models\TransferBooking;
use App\Services\RewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Travel Verticals",
 *     description="Search and book Flights, Cars, Experiences, Theme Parks and Transfers"
 * )
 */
class VerticalsController extends Controller
{
    // ==========================================
    // FLIGHTS VERTICAL
    // ==========================================

    /**
     * @OA\Get(
     *     path="/flights/search",
     *     summary="Search for flights (Mock)",
     *     tags={"Travel Verticals"},
     *     @OA\Parameter(name="origin", in="query", required=true, @OA\Schema(type="string"), example="JFK"),
     *     @OA\Parameter(name="destination", in="query", required=true, @OA\Schema(type="string"), example="LAX"),
     *     @OA\Parameter(name="departure_date", in="query", required=true, @OA\Schema(type="string", format="date"), example="2027-01-23"),
     *     @OA\Parameter(name="return_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="passengers", in="query", @OA\Schema(type="integer"), example=1),
     *     @OA\Response(response=200, description="List of available flights")
     * )
     */
    public function searchFlights(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required|string|size:3',
            'destination' => 'required|string|size:3',
            'departure_date' => 'required|date|after_or_equal:today',
            'return_date' => 'nullable|date|after:departure_date',
            'passengers' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $airlines = ['Delta Air Lines', 'United Airlines', 'American Airlines', 'JetBlue', 'Spirit Airlines'];
        $airports = [
            'JFK' => 'John F. Kennedy International Airport',
            'LAX' => 'Los Angeles International Airport',
            'MIA' => 'Miami International Airport',
            'SFO' => 'San Francisco International Airport',
            'ORD' => 'O\'Hare International Airport',
        ];

        $flights = [];
        $passengers = $request->passengers ?? 1;

        for ($i = 1; $i <= 5; $i++) {
            $basePrice = 150 + ($i * 65);
            $airline = $airlines[$i - 1];
            $departureTime = \Carbon\Carbon::parse($request->departure_date)->addHours(6 + ($i * 2));
            $arrivalTime = $departureTime->copy()->addHours(3)->addMinutes($i * 15);

            $flights[] = [
                'id' => 'FL-' . Str::random(8),
                'airline' => $airline,
                'flight_number' => $airline[0] . $airline[1] . '-' . (100 + $i * 47),
                'departure' => [
                    'code' => strtoupper($request->origin),
                    'airport' => $airports[strtoupper($request->origin)] ?? 'Airport Name',
                    'time' => $departureTime->toDateTimeString(),
                ],
                'arrival' => [
                    'code' => strtoupper($request->destination),
                    'airport' => $airports[strtoupper($request->destination)] ?? 'Airport Name',
                    'time' => $arrivalTime->toDateTimeString(),
                ],
                'stops' => $i % 3 === 0 ? 1 : 0,
                'class' => 'Economy',
                'pricing' => [
                    'base_fare' => $basePrice,
                    'taxes' => $basePrice * 0.15,
                    'total_price' => ($basePrice * 1.15) * $passengers,
                    'currency' => 'USD',
                ]
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $flights,
            'search_parameters' => $request->all()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/flights/book",
     *     summary="Book a flight",
     *     tags={"Travel Verticals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"airline", "flight_number", "departure_airport", "arrival_airport", "departure_time", "arrival_time", "passenger_details", "total_price"},
     *         @OA\Property(property="airline", type="string", example="Delta Air Lines"),
     *         @OA\Property(property="flight_number", type="string", example="DL-241"),
     *         @OA\Property(property="departure_airport", type="string", example="JFK"),
     *         @OA\Property(property="arrival_airport", type="string", example="LAX"),
     *         @OA\Property(property="departure_time", type="string", format="date-time", example="2027-01-23 08:00:00"),
     *         @OA\Property(property="arrival_time", type="string", format="date-time", example="2027-01-23 11:30:00"),
     *         @OA\Property(property="passenger_details", type="array", @OA\Items(
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="passport_number", type="string", example="A1234567")
     *         )),
     *         @OA\Property(property="total_price", type="number", example=287.50)
     *     )),
     *     @OA\Response(response=201, description="Flight booking created")
     * )
     */
    public function bookFlight(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'airline' => 'required|string',
            'flight_number' => 'required|string',
            'departure_airport' => 'required|string|size:3',
            'arrival_airport' => 'required|string|size:3',
            'departure_time' => 'required|date',
            'arrival_time' => 'required|date|after:departure_time',
            'passenger_details' => 'required|array|min:1',
            'passenger_details.*.first_name' => 'required|string',
            'passenger_details.*.last_name' => 'required|string',
            'total_price' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $ref = 'FLG-' . strtoupper(Str::random(10));
            $booking = FlightBooking::create([
                'user_id' => auth()->id(),
                'booking_reference' => $ref,
                'airline' => $request->airline,
                'flight_number' => $request->flight_number,
                'departure_airport' => strtoupper($request->departure_airport),
                'arrival_airport' => strtoupper($request->arrival_airport),
                'departure_time' => $request->departure_time,
                'arrival_time' => $request->arrival_time,
                'passenger_details' => $request->passenger_details,
                'total_price' => $request->total_price,
                'currency' => $request->currency ?? 'USD',
                'status' => 'confirmed',
            ]);

            // Earn reward points on booking
            try {
                // Construct a mock Booking model wrapper so we can reuse RewardService
                $mockBooking = new \App\Models\Booking([
                    'user_id' => auth()->id(),
                    'booking_reference' => $ref,
                    'total_price' => $request->total_price,
                    'currency' => $request->currency ?? 'USD',
                    'booking_status' => 'confirmed'
                ]);
                // Set id manually to prevent query error
                $mockBooking->id = $booking->id;
                app(RewardService::class)->earnPointsForBooking($mockBooking);
            } catch (\Exception $rewardEx) {
                Log::error('Flight booking reward points failure: ' . $rewardEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Flight booked successfully.',
                'data' => $booking
            ], 201);

        } catch (\Exception $e) {
            Log::error('Flight booking error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Flight booking failed.'], 500);
        }
    }


    // ==========================================
    // CARS VERTICAL
    // ==========================================

    /**
     * @OA\Get(
     *     path="/cars/search",
     *     summary="Search for car rentals (Mock)",
     *     tags={"Travel Verticals"},
     *     @OA\Parameter(name="location", in="query", required=true, @OA\Schema(type="string"), example="MIA"),
     *     @OA\Parameter(name="pickup_date", in="query", required=true, @OA\Schema(type="string", format="date"), example="2027-01-23"),
     *     @OA\Parameter(name="dropoff_date", in="query", required=true, @OA\Schema(type="string", format="date"), example="2027-01-25"),
     *     @OA\Response(response=200, description="List of available cars")
     * )
     */
    public function searchCars(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string',
            'pickup_date' => 'required|date|after_or_equal:today',
            'dropoff_date' => 'required|date|after:pickup_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $companies = ['Hertz', 'Enterprise', 'Avis', 'Budget', 'Sixt'];
        $models = [
            'Economy' => 'Chevrolet Spark',
            'Compact' => 'Nissan Versa',
            'Intermediate' => 'Hyundai Elantra',
            'Fullsize' => 'Toyota Camry',
            'SUV' => 'Ford Explorer',
        ];

        $cars = [];
        $days = \Carbon\Carbon::parse($request->pickup_date)->diffInDays(\Carbon\Carbon::parse($request->dropoff_date));

        $idx = 0;
        foreach ($models as $class => $model) {
            $company = $companies[$idx % 5];
            $dailyRate = 35 + ($idx * 12);
            $idx++;

            $cars[] = [
                'id' => 'CAR-' . Str::random(8),
                'rental_company' => $company,
                'car_model' => $model,
                'car_class' => $class,
                'pickup_location' => $request->location,
                'dropoff_location' => $request->location,
                'transmission' => 'Automatic',
                'mileage' => 'Unlimited',
                'pricing' => [
                    'daily_rate' => $dailyRate,
                    'total_price' => $dailyRate * $days,
                    'currency' => 'USD',
                ]
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $cars,
            'search_parameters' => $request->all()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/cars/book",
     *     summary="Book a car rental",
     *     tags={"Travel Verticals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"rental_company", "car_model", "car_class", "pickup_location", "dropoff_location", "pickup_time", "dropoff_time", "driver_details", "total_price"},
     *         @OA\Property(property="rental_company", type="string", example="Hertz"),
     *         @OA\Property(property="car_model", type="string", example="Toyota Camry"),
     *         @OA\Property(property="car_class", type="string", example="Fullsize"),
     *         @OA\Property(property="pickup_location", type="string", example="MIA Airport"),
     *         @OA\Property(property="dropoff_location", type="string", example="MIA Airport"),
     *         @OA\Property(property="pickup_time", type="string", format="date-time", example="2027-01-23 10:00:00"),
     *         @OA\Property(property="dropoff_time", type="string", format="date-time", example="2027-01-25 10:00:00"),
     *         @OA\Property(property="driver_details", type="object",
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="license_number", type="string", example="DL123456")
     *         ),
     *         @OA\Property(property="total_price", type="number", example=118.00)
     *     )),
     *     @OA\Response(response=201, description="Car booking created")
     * )
     */
    public function bookCar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_company' => 'required|string',
            'car_model' => 'required|string',
            'car_class' => 'required|string',
            'pickup_location' => 'required|string',
            'dropoff_location' => 'required|string',
            'pickup_time' => 'required|date',
            'dropoff_time' => 'required|date|after:pickup_time',
            'driver_details' => 'required|array',
            'driver_details.first_name' => 'required|string',
            'driver_details.last_name' => 'required|string',
            'driver_details.license_number' => 'required|string',
            'total_price' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $ref = 'CAR-' . strtoupper(Str::random(10));
            $booking = CarBooking::create([
                'user_id' => auth()->id(),
                'booking_reference' => $ref,
                'rental_company' => $request->rental_company,
                'car_model' => $request->car_model,
                'car_class' => $request->car_class,
                'pickup_location' => $request->pickup_location,
                'dropoff_location' => $request->dropoff_location,
                'pickup_time' => $request->pickup_time,
                'dropoff_time' => $request->dropoff_time,
                'driver_details' => $request->driver_details,
                'total_price' => $request->total_price,
                'currency' => $request->currency ?? 'USD',
                'status' => 'confirmed',
            ]);

            // Earn reward points on booking
            try {
                $mockBooking = new \App\Models\Booking([
                    'user_id' => auth()->id(),
                    'booking_reference' => $ref,
                    'total_price' => $request->total_price,
                    'currency' => $request->currency ?? 'USD',
                    'booking_status' => 'confirmed'
                ]);
                $mockBooking->id = $booking->id;
                app(RewardService::class)->earnPointsForBooking($mockBooking);
            } catch (\Exception $rewardEx) {
                Log::error('Car booking reward points failure: ' . $rewardEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Car rental booked successfully.',
                'data' => $booking
            ], 201);

        } catch (\Exception $e) {
            Log::error('Car booking error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Car rental booking failed.'], 500);
        }
    }


    // ==========================================
    // EXPERIENCES & THEME PARKS VERTICAL
    // ==========================================

    /**
     * @OA\Get(
     *     path="/experiences/search",
     *     summary="Search for experiences & theme parks (Mock)",
     *     tags={"Travel Verticals"},
     *     @OA\Parameter(name="location", in="query", required=true, @OA\Schema(type="string"), example="Orlando"),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="List of experiences")
     * )
     */
    public function searchExperiences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string',
            'date' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $loc = strtolower($request->location);
        $options = [];

        if (str_contains($loc, 'orland') || str_contains($loc, 'disney')) {
            $options = [
                ['name' => 'Magic Kingdom Disney Pass', 'category' => 'theme_park', 'price' => 125],
                ['name' => 'Universal Studios 1-Day Ticket', 'category' => 'theme_park', 'price' => 119],
                ['name' => 'Everglades Airboat Tour', 'category' => 'experience', 'price' => 45],
            ];
        } elseif (str_contains($loc, 'miami')) {
            $options = [
                ['name' => 'Miami Jet Ski Rental', 'category' => 'experience', 'price' => 85],
                ['name' => 'Millionaire\'s Row Cruise', 'category' => 'experience', 'price' => 30],
                ['name' => 'Key West Day Trip from Miami', 'category' => 'experience', 'price' => 69],
            ];
        } else {
            $options = [
                ['name' => 'City Hop-on Hop-off Bus Tour', 'category' => 'experience', 'price' => 39],
                ['name' => 'Guided Historic Walking Tour', 'category' => 'experience', 'price' => 25],
                ['name' => 'Local Food & Wine Tasting', 'category' => 'experience', 'price' => 75],
            ];
        }

        $data = [];
        foreach ($options as $idx => $opt) {
            $data[] = [
                'id' => 'EXP-' . Str::random(8),
                'name' => $opt['name'],
                'category' => $opt['category'],
                'location' => $request->location,
                'duration' => $opt['category'] === 'theme_park' ? 'Full Day' : '2-4 Hours',
                'rating' => 4.5 + ($idx * 0.1),
                'pricing' => [
                    'price_per_ticket' => $opt['price'],
                    'currency' => 'USD',
                ]
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'search_parameters' => $request->all()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/experiences/book",
     *     summary="Book tickets for an experience / theme park",
     *     tags={"Travel Verticals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"experience_name", "category", "activity_date", "quantity", "ticket_details", "total_price"},
     *         @OA\Property(property="experience_name", type="string", example="Universal Studios 1-Day Ticket"),
     *         @OA\Property(property="category", type="string", enum={"experience", "theme_park"}),
     *         @OA\Property(property="activity_date", type="string", format="date", example="2027-01-23"),
     *         @OA\Property(property="quantity", type="integer", example=2),
     *         @OA\Property(property="ticket_details", type="array", @OA\Items(
     *             @OA\Property(property="name", type="string", example="Jane Doe"),
     *             @OA\Property(property="type", type="string", example="Adult")
     *         )),
     *         @OA\Property(property="total_price", type="number", example=238.00)
     *     )),
     *     @OA\Response(response=201, description="Experience booking created")
     * )
     */
    public function bookExperience(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'experience_name' => 'required|string',
            'category' => 'required|in:experience,theme_park',
            'activity_date' => 'required|date',
            'quantity' => 'required|integer|min:1',
            'ticket_details' => 'required|array',
            'total_price' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $ref = 'EXP-' . strtoupper(Str::random(10));
            $booking = ExperienceBooking::create([
                'user_id' => auth()->id(),
                'booking_reference' => $ref,
                'experience_name' => $request->experience_name,
                'category' => $request->category,
                'activity_date' => $request->activity_date,
                'quantity' => $request->quantity,
                'ticket_details' => $request->ticket_details,
                'total_price' => $request->total_price,
                'currency' => $request->currency ?? 'USD',
                'status' => 'confirmed',
            ]);

            // Earn reward points on booking
            try {
                $mockBooking = new \App\Models\Booking([
                    'user_id' => auth()->id(),
                    'booking_reference' => $ref,
                    'total_price' => $request->total_price,
                    'currency' => $request->currency ?? 'USD',
                    'booking_status' => 'confirmed'
                ]);
                $mockBooking->id = $booking->id;
                app(RewardService::class)->earnPointsForBooking($mockBooking);
            } catch (\Exception $rewardEx) {
                Log::error('Experience booking reward points failure: ' . $rewardEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Experience tickets booked successfully.',
                'data' => $booking
            ], 201);

        } catch (\Exception $e) {
            Log::error('Experience booking error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Experience booking failed.'], 500);
        }
    }


    // ==========================================
    // TRANSFERS VERTICAL
    // ==========================================

    /**
     * @OA\Get(
     *     path="/transfers/search",
     *     summary="Search for ground transfers (Mock)",
     *     tags={"Travel Verticals"},
     *     @OA\Parameter(name="pickup_location", in="query", required=true, @OA\Schema(type="string"), example="MIA Airport"),
     *     @OA\Parameter(name="dropoff_location", in="query", required=true, @OA\Schema(type="string"), example="South Beach"),
     *     @OA\Parameter(name="date", in="query", required=true, @OA\Schema(type="string", format="date"), example="2027-01-23"),
     *     @OA\Response(response=200, description="List of transfers")
     * )
     */
    public function searchTransfers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickup_location' => 'required|string',
            'dropoff_location' => 'required|string',
            'date' => 'required|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $transfers = [
            [
                'id' => 'TRN-' . Str::random(8),
                'transfer_type' => 'shared_shuttle',
                'name' => 'Shared Airport Shuttle',
                'vehicle' => 'Ford Transit or similar',
                'capacity' => 'Up to 12 passengers',
                'pricing' => [
                    'price' => 18.00,
                    'currency' => 'USD',
                ]
            ],
            [
                'id' => 'TRN-' . Str::random(8),
                'transfer_type' => 'private_sedan',
                'name' => 'Private Sedan Transfer',
                'vehicle' => 'Toyota Camry or similar',
                'capacity' => 'Up to 3 passengers',
                'pricing' => [
                    'price' => 55.00,
                    'currency' => 'USD',
                ]
            ],
            [
                'id' => 'TRN-' . Str::random(8),
                'transfer_type' => 'private_suv',
                'name' => 'Private Luxury SUV',
                'vehicle' => 'Cadillac Escalade or similar',
                'capacity' => 'Up to 6 passengers',
                'pricing' => [
                    'price' => 95.00,
                    'currency' => 'USD',
                ]
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $transfers,
            'search_parameters' => $request->all()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/transfers/book",
     *     summary="Book a ground transfer",
     *     tags={"Travel Verticals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"pickup_location", "dropoff_location", "transfer_time", "transfer_type", "passenger_count", "total_price"},
     *         @OA\Property(property="pickup_location", type="string", example="MIA Airport"),
     *         @OA\Property(property="dropoff_location", type="string", example="South Beach"),
     *         @OA\Property(property="transfer_time", type="string", format="date-time", example="2027-01-23 15:30:00"),
     *         @OA\Property(property="transfer_type", type="string", example="private_sedan"),
     *         @OA\Property(property="passenger_count", type="integer", example=2),
     *         @OA\Property(property="total_price", type="number", example=55.00)
     *     )),
     *     @OA\Response(response=201, description="Transfer booking created")
     * )
     */
    public function bookTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickup_location' => 'required|string',
            'dropoff_location' => 'required|string',
            'transfer_time' => 'required|date',
            'transfer_type' => 'required|string',
            'passenger_count' => 'required|integer|min:1',
            'total_price' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $ref = 'TRN-' . strtoupper(Str::random(10));
            $booking = TransferBooking::create([
                'user_id' => auth()->id(),
                'booking_reference' => $ref,
                'pickup_location' => $request->pickup_location,
                'dropoff_location' => $request->dropoff_location,
                'transfer_time' => $request->transfer_time,
                'transfer_type' => $request->transfer_type,
                'passenger_count' => $request->passenger_count,
                'total_price' => $request->total_price,
                'currency' => $request->currency ?? 'USD',
                'status' => 'confirmed',
            ]);

            // Earn reward points on booking
            try {
                $mockBooking = new \App\Models\Booking([
                    'user_id' => auth()->id(),
                    'booking_reference' => $ref,
                    'total_price' => $request->total_price,
                    'currency' => $request->currency ?? 'USD',
                    'booking_status' => 'confirmed'
                ]);
                $mockBooking->id = $booking->id;
                app(RewardService::class)->earnPointsForBooking($mockBooking);
            } catch (\Exception $rewardEx) {
                Log::error('Transfer booking reward points failure: ' . $rewardEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Ground transfer booked successfully.',
                'data' => $booking
            ], 201);

        } catch (\Exception $e) {
            Log::error('Transfer booking error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ground transfer booking failed.'], 500);
        }
    }
}
