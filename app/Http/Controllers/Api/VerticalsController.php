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
use App\Models\Flight;
use App\Models\Car;
use App\Models\Experience;
use App\Models\Transfer;

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
        try {
            // Auto-detect and normalize inputs for maximum frontend compatibility
            $origin = $request->origin;
            $destination = $request->destination ?? $request->location;
            $departureDate = $request->departure_date ?? $request->date ?? $request->checkIn ?? date('Y-m-d');
            $passengers = $request->passengers ?? 1;

            // Map location string or code to airport codes if not a 3-letter IATA code
            $airportMapping = [
                'dubai' => 'DXB',
                'london' => 'LHR',
                'miami' => 'MIA',
                'new york' => 'JFK',
                'paris' => 'CDG',
                'los angeles' => 'LAX',
                'san francisco' => 'SFO',
                'chicago' => 'ORD',
            ];

            if ($destination && strlen($destination) > 3) {
                $normDest = strtolower(trim($destination));
                foreach ($airportMapping as $key => $code) {
                    if (str_contains($normDest, $key)) {
                        $destination = $code;
                        break;
                    }
                }
            }

            if (!$destination || strlen($destination) !== 3) {
                $destination = 'DXB'; // Fallback
            }

            if ($origin && strlen($origin) > 3) {
                $normOrig = strtolower(trim($origin));
                foreach ($airportMapping as $key => $code) {
                    if (str_contains($normOrig, $key)) {
                        $origin = $code;
                        break;
                    }
                }
            }

            if (!$origin || strlen($origin) !== 3) {
                $origin = ($destination === 'JFK') ? 'DXB' : 'JFK'; // Make sure they don't match
            }

            $origin = strtoupper($origin);
            $destination = strtoupper($destination);

            $flights = [];

            // 1. Fetch manual flights from database first
            $dbFlights = Flight::where('departure_airport_code', $origin)
                ->where('arrival_airport_code', $destination)
                ->where('is_active', true)
                ->get();

            // If we don't find exact airport match, search by city name/like match
            if ($dbFlights->isEmpty() && ($request->destination || $request->location)) {
                $searchLoc = $request->destination ?? $request->location;
                $dbFlights = Flight::where('is_active', true)
                    ->where(function($query) use ($searchLoc) {
                        $query->where('arrival_airport_code', 'like', "%{$searchLoc}%")
                              ->orWhere('arrival_airport_name', 'like', "%{$searchLoc}%");
                    })
                    ->get();
            }

            $departureDateStr = \Carbon\Carbon::parse($departureDate)->format('Y-m-d');

            foreach ($dbFlights as $dbFlight) {
                $depTime = \Carbon\Carbon::parse($departureDateStr . ' ' . $dbFlight->departure_time);
                $arrTime = \Carbon\Carbon::parse($departureDateStr . ' ' . $dbFlight->arrival_time);
                if ($arrTime->lessThan($depTime)) {
                    $arrTime->addDay(); // Handle overnight flights
                }

                $totalPrice = ((float)$dbFlight->base_fare + (float)$dbFlight->taxes) * $passengers;

                $flights[] = [
                    'id' => $dbFlight->id,
                    'airline' => $dbFlight->airline,
                    'flight_number' => $dbFlight->flight_number,
                    'departure_airport' => strtoupper($dbFlight->departure_airport_code),
                    'arrival_airport' => strtoupper($dbFlight->arrival_airport_code),
                    'departure_time' => $depTime->toDateTimeString(),
                    'arrival_time' => $arrTime->toDateTimeString(),
                    'price' => $totalPrice,
                    'currency' => $dbFlight->currency,
                    'cabin_class' => $dbFlight->class,
                    'stops' => (int)$dbFlight->stops,

                    // Nested structures for backward / other page compatibility
                    'departure' => [
                        'code' => strtoupper($dbFlight->departure_airport_code),
                        'airport' => $dbFlight->departure_airport_name,
                        'time' => $depTime->toDateTimeString(),
                    ],
                    'arrival' => [
                        'code' => strtoupper($dbFlight->arrival_airport_code),
                        'airport' => $dbFlight->departure_airport_name,
                        'time' => $arrTime->toDateTimeString(),
                    ],
                    'class' => $dbFlight->class,
                    'pricing' => [
                        'base_fare' => (float)$dbFlight->base_fare,
                        'taxes' => (float)$dbFlight->taxes,
                        'total_price' => $totalPrice,
                        'currency' => $dbFlight->currency,
                    ]
                ];
            }

            // 2. Generate Mocks as fallback/fill up to ensure at least 5 flights are returned
            $airlines = ['Delta Air Lines', 'United Airlines', 'American Airlines', 'JetBlue', 'Spirit Airlines'];
            $airports = [
                'JFK' => 'John F. Kennedy International Airport',
                'LAX' => 'Los Angeles International Airport',
                'MIA' => 'Miami International Airport',
                'SFO' => 'San Francisco International Airport',
                'ORD' => 'O\'Hare International Airport',
                'DXB' => 'Dubai International Airport',
                'LHR' => 'London Heathrow Airport',
                'CDG' => 'Charles de Gaulle Airport',
            ];

            $countNeeded = 5 - count($flights);
            for ($i = 1; $i <= $countNeeded; $i++) {
                $basePrice = 150 + ($i * 65);
                $airline = $airlines[($i - 1) % count($airlines)];
                $departureTime = \Carbon\Carbon::parse($departureDate)->addHours(6 + ($i * 2));
                $arrivalTime = $departureTime->copy()->addHours(3)->addMinutes($i * 15);
                $totalPrice = ($basePrice * 1.15) * $passengers;

                $flights[] = [
                    'id' => 1000 + $i,
                    'airline' => $airline,
                    'flight_number' => $airline[0] . $airline[1] . '-' . (100 + $i * 47),
                    'departure_airport' => $origin,
                    'arrival_airport' => $destination,
                    'departure_time' => $departureTime->toDateTimeString(),
                    'arrival_time' => $arrivalTime->toDateTimeString(),
                    'price' => $totalPrice,
                    'currency' => 'USD',
                    'cabin_class' => 'Economy',
                    'stops' => $i % 3 === 0 ? 1 : 0,

                    // Nested structures for backward / other page compatibility
                    'departure' => [
                        'code' => $origin,
                        'airport' => $airports[$origin] ?? 'Airport Name',
                        'time' => $departureTime->toDateTimeString(),
                    ],
                    'arrival' => [
                        'code' => $destination,
                        'airport' => $airports[$destination] ?? 'Airport Name',
                        'time' => $arrivalTime->toDateTimeString(),
                    ],
                    'class' => 'Economy',
                    'pricing' => [
                        'base_fare' => $basePrice,
                        'taxes' => $basePrice * 0.15,
                        'total_price' => $totalPrice,
                        'currency' => 'USD',
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $flights,
                'search_parameters' => [
                    'origin' => $origin,
                    'destination' => $destination,
                    'departure_date' => $departureDate,
                    'passengers' => $passengers
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Flight search error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Flight search failed: ' . $e->getMessage()], 200);
        }
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
            return response()->json(['success' => false, 'message' => 'Flight booking failed: ' . $e->getMessage()], 200);
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
        try {
            // Auto-detect and normalize parameters
            $location = $request->location ?? 'Dubai';
            $pickupDate = $request->pickup_date ?? $request->checkIn ?? date('Y-m-d');
            $dropoffDate = $request->dropoff_date ?? $request->checkOut ?? date('Y-m-d', strtotime('+2 days'));

            $days = \Carbon\Carbon::parse($pickupDate)->diffInDays(\Carbon\Carbon::parse($dropoffDate));
            if ($days <= 0) $days = 1;

            $cars = [];

            // 1. Fetch manual cars from database first
            $dbCars = Car::where('is_active', true)
                ->where(function($query) use ($location) {
                    $query->where('pickup_location', 'like', "%{$location}%")
                          ->orWhere('dropoff_location', 'like', "%{$location}%");
                })
                ->get();

            // If no match, try general lookup
            if ($dbCars->isEmpty()) {
                $dbCars = Car::where('is_active', true)->get();
            }

            foreach ($dbCars as $dbCar) {
                $totalPrice = (float)$dbCar->daily_rate * $days;
                $cars[] = [
                    'id' => $dbCar->id,
                    'rental_company' => $dbCar->rental_company,
                    'car_model' => $dbCar->car_model,
                    'car_class' => $dbCar->car_class,
                    'pickup_location' => $dbCar->pickup_location,
                    'dropoff_location' => $dbCar->dropoff_location,
                    'transmission' => $dbCar->transmission,
                    'fuel_type' => $dbCar->fuel_type,
                    'mileage' => $dbCar->mileage,
                    'price_per_day' => (float)$dbCar->daily_rate,
                    'currency' => $dbCar->currency,

                    // Nested structures for other pages compatibility
                    'pricing' => [
                        'daily_rate' => (float)$dbCar->daily_rate,
                        'total_price' => $totalPrice,
                        'currency' => $dbCar->currency,
                    ]
                ];
            }

            // 2. Generate Mocks as fallback/fill up to ensure at least 5 cars are returned
            $companies = ['Hertz', 'Enterprise', 'Avis', 'Budget', 'Sixt'];
            $models = [
                'Economy' => 'Chevrolet Spark',
                'Compact' => 'Nissan Versa',
                'Intermediate' => 'Hyundai Elantra',
                'Fullsize' => 'Toyota Camry',
                'SUV' => 'Ford Explorer',
            ];

            $idx = count($cars);
            foreach ($models as $class => $model) {
                if (count($cars) >= 5) break;

                $company = $companies[$idx % 5];
                $dailyRate = 35 + ($idx * 12);
                $totalPrice = $dailyRate * $days;
                $idx++;

                $cars[] = [
                    'id' => 1000 + $idx,
                    'rental_company' => $company,
                    'car_model' => $model,
                    'car_class' => $class,
                    'pickup_location' => $location,
                    'dropoff_location' => $location,
                    'transmission' => 'Automatic',
                    'fuel_type' => 'Petrol',
                    'mileage' => 'Unlimited',
                    'price_per_day' => $dailyRate,
                    'currency' => 'USD',

                    // Nested structures
                    'pricing' => [
                        'daily_rate' => $dailyRate,
                        'total_price' => $totalPrice,
                        'currency' => 'USD',
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $cars,
                'search_parameters' => [
                    'location' => $location,
                    'pickup_date' => $pickupDate,
                    'dropoff_date' => $dropoffDate
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Car search error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Car search failed: ' . $e->getMessage()], 200);
        }
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
        try {
            $carId = $request->car_id;
            
            $rentalCompany = $request->rental_company;
            $carModel = $request->car_model;
            $carClass = $request->car_class;
            $pickupLocation = $request->pickup_location;
            $dropoffLocation = $request->dropoff_location;
            $pickupTime = $request->pickup_time;
            $dropoffTime = $request->dropoff_time;
            $totalPrice = $request->total_price;
            $currency = $request->currency ?? 'USD';

            // Extract driver details
            $driverDetails = $request->driver_details;
            if (!$driverDetails || !is_array($driverDetails)) {
                $driverDetails = [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                    'license_number' => 'DL987654',
                    'phone' => '+15550000'
                ];
            }

            if ($carId) {
                $dbCar = Car::find($carId);
                if ($dbCar) {
                    $rentalCompany = $dbCar->rental_company;
                    $carModel = $dbCar->car_model;
                    $carClass = $dbCar->car_class;
                    $pickupLocation = $dbCar->pickup_location;
                    $dropoffLocation = $dbCar->dropoff_location;
                    $pickupTime = date('Y-m-d H:i:s', strtotime('+1 day 10:00:00'));
                    $dropoffTime = date('Y-m-d H:i:s', strtotime('+3 days 10:00:00'));
                    $totalPrice = (float)$dbCar->daily_rate * 2;
                    $currency = $dbCar->currency;
                } else {
                    // Mock car ID lookup
                    $idx = ((int)$carId) - 1000;
                    if ($idx < 1) $idx = 1;
                    $companies = ['Hertz', 'Enterprise', 'Avis', 'Budget', 'Sixt'];
                    $rentalCompany = $companies[($idx - 1) % 5];
                    $carModel = 'Chevrolet Spark';
                    $carClass = 'Economy';
                    $pickupLocation = $request->location ?? 'Dubai';
                    $dropoffLocation = $request->location ?? 'Dubai';
                    $pickupTime = date('Y-m-d H:i:s', strtotime('+1 day 10:00:00'));
                    $dropoffTime = date('Y-m-d H:i:s', strtotime('+3 days 10:00:00'));
                    $totalPrice = (35 + ($idx * 12)) * 2;
                }
            }

            // Fallbacks
            $rentalCompany = $rentalCompany ?? 'Hertz';
            $carModel = $carModel ?? 'Toyota Camry';
            $carClass = $carClass ?? 'Fullsize';
            $pickupLocation = $pickupLocation ?? 'Dubai Airport';
            $dropoffLocation = $dropoffLocation ?? 'Dubai Airport';
            $pickupTime = $pickupTime ?? date('Y-m-d H:i:s');
            $dropoffTime = $dropoffTime ?? date('Y-m-d H:i:s', strtotime('+2 days'));
            $totalPrice = $totalPrice ?? 150.00;

            $ref = 'CAR-' . strtoupper(Str::random(10));
            $booking = CarBooking::create([
                'user_id' => auth()->id() ?? 1,
                'booking_reference' => $ref,
                'rental_company' => $rentalCompany,
                'car_model' => $carModel,
                'car_class' => $carClass,
                'pickup_location' => $pickupLocation,
                'dropoff_location' => $dropoffLocation,
                'pickup_time' => $pickupTime,
                'dropoff_time' => $dropoffTime,
                'driver_details' => $driverDetails,
                'total_price' => $totalPrice,
                'currency' => $currency,
                'status' => 'confirmed',
            ]);

            // Earn reward points on booking
            try {
                if (auth()->check()) {
                    $mockBooking = new \App\Models\Booking([
                        'user_id' => auth()->id(),
                        'booking_reference' => $ref,
                        'total_price' => $totalPrice,
                        'currency' => $currency,
                        'booking_status' => 'confirmed'
                    ]);
                    $mockBooking->id = $booking->id;
                    app(RewardService::class)->earnPointsForBooking($mockBooking);
                }
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
            return response()->json(['success' => false, 'message' => 'Car rental booking failed: ' . $e->getMessage()], 200);
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
        try {
            // Auto-detect and normalize inputs
            $location = $request->location ?? 'Dubai';
            $date = $request->date ?? date('Y-m-d');

            $data = [];

            // 1. Fetch manual experiences from database first
            $dbExperiences = Experience::where('is_active', true)
                ->where('location', 'like', "%{$location}%")
                ->get();

            // If no match, try general lookup
            if ($dbExperiences->isEmpty()) {
                $dbExperiences = Experience::where('is_active', true)->get();
            }

            foreach ($dbExperiences as $dbExp) {
                $data[] = [
                    'id' => $dbExp->id,
                    'title' => $dbExp->name,
                    'name' => $dbExp->name,
                    'category' => $dbExp->category,
                    'location' => $dbExp->location,
                    'duration' => $dbExp->duration,
                    'price' => (float)$dbExp->price_per_ticket,
                    'price_per_ticket' => (float)$dbExp->price_per_ticket,
                    'currency' => $dbExp->currency,
                    'rating_average' => (string)$dbExp->rating,
                    'rating' => (float)$dbExp->rating,
                    'rating_count' => 24,

                    // Nested structures for other pages compatibility
                    'pricing' => [
                        'price_per_ticket' => (float)$dbExp->price_per_ticket,
                        'currency' => $dbExp->currency,
                    ]
                ];
            }

            // 2. Generate Mocks as fallback/fill up to ensure at least 3 experiences are returned
            if (count($data) < 3) {
                $loc = strtolower($location);
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

                foreach ($options as $idx => $opt) {
                    $exists = false;
                    foreach ($data as $d) {
                        if (strtolower($d['name']) === strtolower($opt['name'])) {
                            $exists = true;
                            break;
                        }
                    }
                    if ($exists) continue;

                    $data[] = [
                        'id' => 1000 + $idx,
                        'title' => $opt['name'],
                        'name' => $opt['name'],
                        'category' => $opt['category'],
                        'location' => $location,
                        'duration' => $opt['category'] === 'theme_park' ? 'Full Day' : '2-4 Hours',
                        'rating_average' => (string)(4.5 + ($idx * 0.1)),
                        'rating' => 4.5 + ($idx * 0.1),
                        'rating_count' => 12,
                        'price' => (float)$opt['price'],
                        'price_per_ticket' => (float)$opt['price'],
                        'currency' => 'USD',

                        // Nested structures
                        'pricing' => [
                            'price_per_ticket' => $opt['price'],
                            'currency' => 'USD',
                        ]
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'search_parameters' => [
                    'location' => $location,
                    'date' => $date
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Experiences search error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Experiences search failed: ' . $e->getMessage()], 200);
        }
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
        try {
            $experienceId = $request->experience_id;
            
            $experienceName = $request->experience_name;
            $category = $request->category;
            $activityDate = $request->activity_date;
            $quantity = $request->quantity ?? $request->ticket_count ?? 1;
            $totalPrice = $request->total_price;
            $currency = $request->currency ?? 'USD';

            // Extract ticket details or booking details
            $ticketDetails = $request->ticket_details ?? $request->booking_details;
            if (!$ticketDetails || !is_array($ticketDetails)) {
                $ticketDetails = [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com'
                ];
            }

            // Extract activity date from ticket details if needed
            if (!$activityDate && isset($ticketDetails['booking_date'])) {
                $activityDate = $ticketDetails['booking_date'];
            }

            if ($experienceId) {
                $dbExp = Experience::find($experienceId);
                if ($dbExp) {
                    $experienceName = $dbExp->name;
                    $category = $dbExp->category;
                    $totalPrice = (float)$dbExp->price_per_ticket * $quantity;
                    $currency = $dbExp->currency;
                } else {
                    // Mock lookup
                    $idx = ((int)$experienceId) - 1000;
                    if ($idx < 1) $idx = 1;
                    $experienceName = 'City Hop-on Hop-off Bus Tour';
                    $category = 'experience';
                    $totalPrice = 39.00 * $quantity;
                }
            }

            // Fallbacks
            $experienceName = $experienceName ?? 'Guided Historic Walking Tour';
            $category = $category ?? 'experience';
            $activityDate = $activityDate ?? date('Y-m-d');
            $totalPrice = $totalPrice ?? 45.00;

            $ref = 'EXP-' . strtoupper(Str::random(10));
            $booking = ExperienceBooking::create([
                'user_id' => auth()->id() ?? 1,
                'booking_reference' => $ref,
                'experience_name' => $experienceName,
                'category' => $category,
                'activity_date' => $activityDate,
                'quantity' => $quantity,
                'ticket_details' => $ticketDetails,
                'total_price' => $totalPrice,
                'currency' => $currency,
                'status' => 'confirmed',
            ]);

            // Earn reward points on booking
            try {
                if (auth()->check()) {
                    $mockBooking = new \App\Models\Booking([
                        'user_id' => auth()->id(),
                        'booking_reference' => $ref,
                        'total_price' => $totalPrice,
                        'currency' => $currency,
                        'booking_status' => 'confirmed'
                    ]);
                    $mockBooking->id = $booking->id;
                    app(RewardService::class)->earnPointsForBooking($mockBooking);
                }
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
            return response()->json(['success' => false, 'message' => 'Experience booking failed: ' . $e->getMessage()], 200);
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
        try {
            // Auto-detect and normalize parameters
            $location = $request->location ?? 'Dubai';
            $pickupLocation = $request->pickup_location ?? ($location . ' Airport');
            $dropoffLocation = $request->dropoff_location ?? ($location . ' Hotel');
            $date = $request->date ?? date('Y-m-d');

            $transfers = [];

            // 1. Fetch manual transfers from database first
            $dbTransfers = Transfer::where('is_active', true)
                ->where(function($query) use ($pickupLocation, $dropoffLocation) {
                    $query->where('pickup_location', 'like', "%{$pickupLocation}%")
                          ->orWhere('dropoff_location', 'like', "%{$dropoffLocation}%");
                })
                ->get();

            // If no match, try general lookup
            if ($dbTransfers->isEmpty()) {
                $dbTransfers = Transfer::where('is_active', true)->get();
            }

            foreach ($dbTransfers as $dbTrn) {
                // Parse capacity number
                $maxPassengers = (int)filter_var($dbTrn->capacity, FILTER_SANITIZE_NUMBER_INT);
                if ($maxPassengers <= 0) $maxPassengers = 4;

                $transfers[] = [
                    'id' => $dbTrn->id,
                    'provider' => $dbTrn->name,
                    'name' => $dbTrn->name,
                    'vehicle_type' => $dbTrn->transfer_type,
                    'transfer_type' => $dbTrn->transfer_type,
                    'vehicle' => $dbTrn->vehicle,
                    'max_passengers' => $maxPassengers,
                    'max_luggage' => 2,
                    'price' => (float)$dbTrn->price,
                    'currency' => $dbTrn->currency,
                    'duration_minutes' => 30,

                    // Nested structures for compatibility
                    'pricing' => [
                        'price' => (float)$dbTrn->price,
                        'currency' => $dbTrn->currency,
                    ]
                ];
            }

            // 2. Generate Mocks as fallback/fill up to ensure at least 3 transfers are returned
            if (count($transfers) < 3) {
                $mockTransfers = [
                    [
                        'vehicle_type' => 'Shared Airport Shuttle',
                        'provider' => 'SuperShuttle',
                        'vehicle' => 'Ford Transit or similar',
                        'max_passengers' => 12,
                        'max_luggage' => 12,
                        'price' => 18.00,
                        'currency' => 'USD',
                        'duration_minutes' => 45,
                    ],
                    [
                        'vehicle_type' => 'Private Sedan Transfer',
                        'provider' => 'Blacklane',
                        'vehicle' => 'Toyota Camry or similar',
                        'max_passengers' => 3,
                        'max_luggage' => 3,
                        'price' => 55.00,
                        'currency' => 'USD',
                        'duration_minutes' => 30,
                    ],
                    [
                        'vehicle_type' => 'Private Luxury SUV',
                        'provider' => 'Careem Executive',
                        'vehicle' => 'Cadillac Escalade or similar',
                        'max_passengers' => 6,
                        'max_luggage' => 6,
                        'price' => 95.00,
                        'currency' => 'USD',
                        'duration_minutes' => 30,
                    ]
                ];

                foreach ($mockTransfers as $idx => $mock) {
                    if (count($transfers) >= 3) break;

                    $transfers[] = [
                        'id' => 1000 + $idx,
                        'provider' => $mock['provider'],
                        'name' => $mock['provider'],
                        'vehicle_type' => $mock['vehicle_type'],
                        'transfer_type' => $mock['vehicle_type'],
                        'vehicle' => $mock['vehicle'],
                        'max_passengers' => $mock['max_passengers'],
                        'max_luggage' => $mock['max_luggage'],
                        'price' => $mock['price'],
                        'currency' => $mock['currency'],
                        'duration_minutes' => $mock['duration_minutes'],

                        // Nested structures
                        'pricing' => [
                            'price' => $mock['price'],
                            'currency' => $mock['currency'],
                        ]
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $transfers,
                'search_parameters' => [
                    'pickup_location' => $pickupLocation,
                    'dropoff_location' => $dropoffLocation,
                    'date' => $date
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Transfers search error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Transfers search failed: ' . $e->getMessage()], 200);
        }
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
        try {
            $transferId = $request->transfer_id;
            
            $pickupLocation = $request->pickup_location;
            $dropoffLocation = $request->dropoff_location;
            $transferTime = $request->transfer_time;
            $transferType = $request->transfer_type;
            $passengerCount = $request->passenger_count ?? 1;
            $totalPrice = $request->total_price;
            $currency = $request->currency ?? 'USD';

            // Extract passenger/driver details
            $passengerDetails = $request->passenger_details;
            if (!$passengerDetails || !is_array($passengerDetails)) {
                $passengerDetails = [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                    'phone' => '+15550000',
                    'pickup_time' => '12:00'
                ];
            }

            // Extract values from passenger details if they are in the frontend format
            if (!$transferTime && isset($passengerDetails['pickup_time'])) {
                $transferTime = date('Y-m-d') . ' ' . $passengerDetails['pickup_time'];
            }

            if ($transferId) {
                $dbTrn = Transfer::find($transferId);
                if ($dbTrn) {
                    $pickupLocation = $dbTrn->pickup_location;
                    $dropoffLocation = $dbTrn->dropoff_location;
                    $transferType = $dbTrn->transfer_type;
                    $totalPrice = (float)$dbTrn->price;
                    $currency = $dbTrn->currency;
                } else {
                    // Mock lookup
                    $idx = ((int)$transferId) - 1000;
                    if ($idx < 1) $idx = 1;
                    $pickupLocation = 'Dubai Airport';
                    $dropoffLocation = 'Dubai Hotel';
                    $transferType = 'Private Sedan Transfer';
                    $totalPrice = 55.00;
                }
            }

            // Fallbacks
            $pickupLocation = $pickupLocation ?? 'Dubai Airport';
            $dropoffLocation = $dropoffLocation ?? 'Dubai Hotel';
            $transferTime = $transferTime ?? date('Y-m-d H:i:s');
            $transferType = $transferType ?? 'Private Sedan Transfer';
            $totalPrice = $totalPrice ?? 55.00;

            $ref = 'TRN-' . strtoupper(Str::random(10));
            $booking = TransferBooking::create([
                'user_id' => auth()->id() ?? 1,
                'booking_reference' => $ref,
                'pickup_location' => $pickupLocation,
                'dropoff_location' => $dropoffLocation,
                'transfer_time' => $transferTime,
                'transfer_type' => $transferType,
                'passenger_count' => $passengerCount,
                'total_price' => $totalPrice,
                'currency' => $currency,
                'status' => 'confirmed',
            ]);

            // Earn reward points on booking
            try {
                if (auth()->check()) {
                    $mockBooking = new \App\Models\Booking([
                        'user_id' => auth()->id(),
                        'booking_reference' => $ref,
                        'total_price' => $totalPrice,
                        'currency' => $currency,
                        'booking_status' => 'confirmed'
                    ]);
                    $mockBooking->id = $booking->id;
                    app(RewardService::class)->earnPointsForBooking($mockBooking);
                }
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
            return response()->json(['success' => false, 'message' => 'Ground transfer booking failed: ' . $e->getMessage()], 200);
        }
    }
}
