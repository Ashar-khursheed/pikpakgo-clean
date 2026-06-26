<?php

namespace Database\Seeders;

use App\Models\Flight;
use App\Models\Car;
use App\Models\Experience;
use App\Models\Transfer;
use Illuminate\Database\Seeder;

class TravelVerticalsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -------------------------------------------------------------
        // SEED FLIGHTS (20 records)
        // -------------------------------------------------------------
        $flights = [
            [
                'airline' => 'Delta Air Lines',
                'flight_number' => 'DL-214',
                'departure_airport_code' => 'JFK',
                'departure_airport_name' => 'John F. Kennedy International Airport',
                'arrival_airport_code' => 'LAX',
                'arrival_airport_name' => 'Los Angeles International Airport',
                'departure_time' => '08:00:00',
                'arrival_time' => '11:15:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 180.00,
                'taxes' => 27.50,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Delta Air Lines',
                'flight_number' => 'DL-992',
                'departure_airport_code' => 'LAX',
                'departure_airport_name' => 'Los Angeles International Airport',
                'arrival_airport_code' => 'JFK',
                'arrival_airport_name' => 'John F. Kennedy International Airport',
                'departure_time' => '14:30:00',
                'arrival_time' => '22:45:00',
                'stops' => 0,
                'class' => 'First Class',
                'base_fare' => 450.00,
                'taxes' => 67.50,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Emirates',
                'flight_number' => 'EK-201',
                'departure_airport_code' => 'DXB',
                'departure_airport_name' => 'Dubai International Airport',
                'arrival_airport_code' => 'JFK',
                'arrival_airport_name' => 'John F. Kennedy International Airport',
                'departure_time' => '08:30:00',
                'arrival_time' => '14:20:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 650.00,
                'taxes' => 95.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Emirates',
                'flight_number' => 'EK-202',
                'departure_airport_code' => 'JFK',
                'departure_airport_name' => 'John F. Kennedy International Airport',
                'arrival_airport_code' => 'DXB',
                'arrival_airport_name' => 'Dubai International Airport',
                'departure_time' => '23:00:00',
                'arrival_time' => '20:30:00',
                'stops' => 0,
                'class' => 'Business',
                'base_fare' => 1200.00,
                'taxes' => 180.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'British Airways',
                'flight_number' => 'BA-112',
                'departure_airport_code' => 'JFK',
                'departure_airport_name' => 'John F. Kennedy International Airport',
                'arrival_airport_code' => 'LHR',
                'arrival_airport_name' => 'London Heathrow Airport',
                'departure_time' => '18:30:00',
                'arrival_time' => '06:30:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 320.00,
                'taxes' => 48.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'British Airways',
                'flight_number' => 'BA-113',
                'departure_airport_code' => 'LHR',
                'departure_airport_name' => 'London Heathrow Airport',
                'arrival_airport_code' => 'JFK',
                'arrival_airport_name' => 'John F. Kennedy International Airport',
                'departure_time' => '10:15:00',
                'arrival_time' => '13:30:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 340.00,
                'taxes' => 51.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'United Airlines',
                'flight_number' => 'UA-342',
                'departure_airport_code' => 'ORD',
                'departure_airport_name' => 'O\'Hare International Airport',
                'arrival_airport_code' => 'MIA',
                'arrival_airport_name' => 'Miami International Airport',
                'departure_time' => '09:00:00',
                'arrival_time' => '13:10:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 125.00,
                'taxes' => 18.75,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'United Airlines',
                'flight_number' => 'UA-881',
                'departure_airport_code' => 'MIA',
                'departure_airport_name' => 'Miami International Airport',
                'arrival_airport_code' => 'ORD',
                'arrival_airport_name' => 'O\'Hare International Airport',
                'departure_time' => '16:45:00',
                'arrival_time' => '19:05:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 135.00,
                'taxes' => 20.25,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'American Airlines',
                'flight_number' => 'AA-104',
                'departure_airport_code' => 'MIA',
                'departure_airport_name' => 'Miami International Airport',
                'arrival_airport_code' => 'JFK',
                'arrival_airport_name' => 'John F. Kennedy International Airport',
                'departure_time' => '11:00:00',
                'arrival_time' => '13:55:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 110.00,
                'taxes' => 16.50,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'American Airlines',
                'flight_number' => 'AA-405',
                'departure_airport_code' => 'JFK',
                'departure_airport_name' => 'John F. Kennedy International Airport',
                'arrival_airport_code' => 'MIA',
                'arrival_airport_name' => 'Miami International Airport',
                'departure_time' => '17:30:00',
                'arrival_time' => '20:35:00',
                'stops' => 0,
                'class' => 'Business',
                'base_fare' => 280.00,
                'taxes' => 42.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Air France',
                'flight_number' => 'AF-015',
                'departure_airport_code' => 'CDG',
                'departure_airport_name' => 'Charles de Gaulle Airport',
                'arrival_airport_code' => 'JFK',
                'arrival_airport_name' => 'John F. Kennedy International Airport',
                'departure_time' => '08:30:00',
                'arrival_time' => '10:40:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 410.00,
                'taxes' => 61.50,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Air France',
                'flight_number' => 'AF-022',
                'departure_airport_code' => 'JFK',
                'departure_airport_name' => 'John F. Kennedy International Airport',
                'arrival_airport_code' => 'CDG',
                'arrival_airport_name' => 'Charles de Gaulle Airport',
                'departure_time' => '19:20:00',
                'arrival_time' => '08:45:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 420.00,
                'taxes' => 63.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Lufthansa',
                'flight_number' => 'LH-430',
                'departure_airport_code' => 'ORD',
                'departure_airport_name' => 'O\'Hare International Airport',
                'arrival_airport_code' => 'CDG',
                'arrival_airport_name' => 'Charles de Gaulle Airport',
                'departure_time' => '16:00:00',
                'arrival_time' => '07:15:00',
                'stops' => 1,
                'class' => 'Economy',
                'base_fare' => 480.00,
                'taxes' => 72.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'JetBlue',
                'flight_number' => 'B6-281',
                'departure_airport_code' => 'JFK',
                'departure_airport_name' => 'John F. Kennedy International Airport',
                'arrival_airport_code' => 'SFO',
                'arrival_airport_name' => 'San Francisco International Airport',
                'departure_time' => '10:00:00',
                'arrival_time' => '13:25:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 160.00,
                'taxes' => 24.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'JetBlue',
                'flight_number' => 'B6-282',
                'departure_airport_code' => 'SFO',
                'departure_airport_name' => 'San Francisco International Airport',
                'arrival_airport_code' => 'JFK',
                'arrival_airport_name' => 'John F. Kennedy International Airport',
                'departure_time' => '15:00:00',
                'arrival_time' => '23:35:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 170.00,
                'taxes' => 25.50,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'flydubai',
                'flight_number' => 'FZ-001',
                'departure_airport_code' => 'DXB',
                'departure_airport_name' => 'Dubai International Airport',
                'arrival_airport_code' => 'LHR',
                'arrival_airport_name' => 'London Heathrow Airport',
                'departure_time' => '09:45:00',
                'arrival_time' => '14:15:00',
                'stops' => 1,
                'class' => 'Economy',
                'base_fare' => 380.00,
                'taxes' => 57.00,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'flydubai',
                'flight_number' => 'FZ-002',
                'departure_airport_code' => 'LHR',
                'departure_airport_name' => 'London Heathrow Airport',
                'arrival_airport_code' => 'DXB',
                'arrival_airport_name' => 'Dubai International Airport',
                'departure_time' => '16:00:00',
                'arrival_time' => '02:00:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 410.00,
                'taxes' => 61.50,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Alaska Airlines',
                'flight_number' => 'AS-345',
                'departure_airport_code' => 'SFO',
                'departure_airport_name' => 'San Francisco International Airport',
                'arrival_airport_code' => 'LAX',
                'arrival_airport_name' => 'Los Angeles International Airport',
                'departure_time' => '07:30:00',
                'arrival_time' => '09:05:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 59.00,
                'taxes' => 8.85,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Alaska Airlines',
                'flight_number' => 'AS-346',
                'departure_airport_code' => 'LAX',
                'departure_airport_name' => 'Los Angeles International Airport',
                'arrival_airport_code' => 'SFO',
                'arrival_airport_name' => 'San Francisco International Airport',
                'departure_time' => '18:15:00',
                'arrival_time' => '19:50:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 69.00,
                'taxes' => 10.35,
                'currency' => 'USD',
                'is_active' => true,
            ],
            [
                'airline' => 'Spirit Airlines',
                'flight_number' => 'NK-402',
                'departure_airport_code' => 'ORD',
                'departure_airport_name' => 'O\'Hare International Airport',
                'arrival_airport_code' => 'LAX',
                'arrival_airport_name' => 'Los Angeles International Airport',
                'departure_time' => '20:15:00',
                'arrival_time' => '22:45:00',
                'stops' => 0,
                'class' => 'Economy',
                'base_fare' => 79.00,
                'taxes' => 11.85,
                'currency' => 'USD',
                'is_active' => true,
            ],
        ];

        foreach ($flights as $fData) {
            Flight::create($fData);
        }

        // -------------------------------------------------------------
        // SEED CARS (20 records)
        // -------------------------------------------------------------
        $companies = ['Hertz', 'Enterprise', 'Avis', 'Budget', 'Sixt'];
        $carDetails = [
            ['model' => 'Chevrolet Spark', 'class' => 'Economy', 'transmission' => 'Automatic', 'fuel' => 'Petrol', 'rate' => 35.00],
            ['model' => 'Nissan Versa', 'class' => 'Compact', 'transmission' => 'Automatic', 'fuel' => 'Petrol', 'rate' => 40.00],
            ['model' => 'Hyundai Elantra', 'class' => 'Intermediate', 'transmission' => 'Automatic', 'fuel' => 'Gasoline', 'rate' => 48.00],
            ['model' => 'Toyota Camry', 'class' => 'Fullsize', 'transmission' => 'Automatic', 'fuel' => 'Hybrid', 'rate' => 58.00],
            ['model' => 'Ford Explorer', 'class' => 'SUV', 'transmission' => 'Automatic', 'fuel' => 'Gasoline', 'rate' => 75.00],
            ['model' => 'Tesla Model 3', 'class' => 'Electric', 'transmission' => 'Automatic', 'fuel' => 'Electric', 'rate' => 85.00],
            ['model' => 'BMW 3 Series', 'class' => 'Luxury', 'transmission' => 'Automatic', 'fuel' => 'Petrol', 'rate' => 110.00],
            ['model' => 'Chevrolet Tahoe', 'class' => 'SUV', 'transmission' => 'Automatic', 'fuel' => 'Gasoline', 'rate' => 95.00],
        ];
        $locations = ['Miami', 'New York', 'Los Angeles', 'Orlando', 'Las Vegas', 'Dubai', 'London', 'Paris'];

        for ($i = 0; $i < 20; $i++) {
            $company = $companies[$i % count($companies)];
            $detail = $carDetails[$i % count($carDetails)];
            $loc = $locations[$i % count($locations)];

            Car::create([
                'rental_company' => $company,
                'car_model' => $detail['model'],
                'car_class' => $detail['class'],
                'pickup_location' => $loc,
                'dropoff_location' => $loc,
                'transmission' => $detail['transmission'],
                'fuel_type' => $detail['fuel'],
                'mileage' => 'Unlimited',
                'daily_rate' => $detail['rate'] + ($i * 2),
                'currency' => 'USD',
                'is_active' => true,
            ]);
        }

        // -------------------------------------------------------------
        // SEED EXPERIENCES (20 records)
        // -------------------------------------------------------------
        $experiences = [
            ['name' => 'Miami Jet Ski Rental & City Views', 'category' => 'experience', 'location' => 'Miami', 'duration' => '1 Hour', 'rating' => 4.8, 'price' => 85.00],
            ['name' => 'Millionaire\'s Row Mansion Cruise', 'category' => 'experience', 'location' => 'Miami', 'duration' => '90 Minutes', 'rating' => 4.6, 'price' => 30.00],
            ['name' => 'Everglades Airboat Adventure Tour', 'category' => 'experience', 'location' => 'Miami', 'duration' => '4 Hours', 'rating' => 4.7, 'price' => 45.00],
            ['name' => 'Key West Full-Day Tour from Miami', 'category' => 'experience', 'location' => 'Miami', 'duration' => '12 Hours', 'rating' => 4.5, 'price' => 69.00],
            
            ['name' => 'Summit One Vanderbilt Entry Ticket', 'category' => 'theme_park', 'location' => 'New York', 'duration' => '2 Hours', 'rating' => 4.9, 'price' => 48.00],
            ['name' => 'Statue of Liberty & Ellis Island Guided Tour', 'category' => 'experience', 'location' => 'New York', 'duration' => '4 Hours', 'rating' => 4.8, 'price' => 38.00],
            ['name' => 'Manhattan Helicopter Sightseeing Flight', 'category' => 'experience', 'location' => 'New York', 'duration' => '15 Minutes', 'rating' => 4.9, 'price' => 195.00],
            
            ['name' => 'Warner Bros. Studio Tour Hollywood', 'category' => 'experience', 'location' => 'Los Angeles', 'duration' => '3 Hours', 'rating' => 4.7, 'price' => 75.00],
            ['name' => 'Universal Studios Hollywood General Admission', 'category' => 'theme_park', 'location' => 'Los Angeles', 'duration' => 'Full Day', 'rating' => 4.8, 'price' => 119.00],
            ['name' => 'Beverly Hills & Celebrity Homes Bike Tour', 'category' => 'experience', 'location' => 'Los Angeles', 'duration' => '3 Hours', 'rating' => 4.6, 'price' => 59.00],
            
            ['name' => 'Magic Kingdom Disney Pass (Single Day)', 'category' => 'theme_park', 'location' => 'Orlando', 'duration' => 'Full Day', 'rating' => 4.9, 'price' => 135.00],
            ['name' => 'Universal Orlando Resort 2-Park Pass', 'category' => 'theme_park', 'location' => 'Orlando', 'duration' => 'Full Day', 'rating' => 4.8, 'price' => 164.00],
            ['name' => 'Kennedy Space Center Explorer Admission', 'category' => 'theme_park', 'location' => 'Orlando', 'duration' => '6 Hours', 'rating' => 4.7, 'price' => 75.00],
            
            ['name' => 'Burj Khalifa At the Top (124th Floor)', 'category' => 'experience', 'location' => 'Dubai', 'duration' => '2 Hours', 'rating' => 4.8, 'price' => 49.00],
            ['name' => 'Desert Safari with Dune Bashing & BBQ Buffet', 'category' => 'experience', 'location' => 'Dubai', 'duration' => '6 Hours', 'rating' => 4.9, 'price' => 55.00],
            ['name' => 'Aquaventure Waterpark Entrance Ticket', 'category' => 'theme_park', 'location' => 'Dubai', 'duration' => 'Full Day', 'rating' => 4.7, 'price' => 89.00],
            
            ['name' => 'London Eye Ticket with Fast Track Entry', 'category' => 'experience', 'location' => 'London', 'duration' => '30 Minutes', 'rating' => 4.7, 'price' => 50.00],
            ['name' => 'Tower of London & Crown Jewels Exhibition', 'category' => 'experience', 'location' => 'London', 'duration' => '3 Hours', 'rating' => 4.8, 'price' => 40.00],
            
            ['name' => 'Louvre Museum E-Ticket & Audio Guide', 'category' => 'experience', 'location' => 'Paris', 'duration' => '3 Hours', 'rating' => 4.6, 'price' => 25.00],
            ['name' => 'Eiffel Tower Summit Access & Seine River Cruise', 'category' => 'experience', 'location' => 'Paris', 'duration' => '4 Hours', 'rating' => 4.8, 'price' => 79.00],
        ];

        foreach ($experiences as $exp) {
            Experience::create([
                'name' => $exp['name'],
                'category' => $exp['category'],
                'location' => $exp['location'],
                'duration' => $exp['duration'],
                'rating' => $exp['rating'],
                'price_per_ticket' => $exp['price'],
                'currency' => 'USD',
                'is_active' => true,
            ]);
        }

        // -------------------------------------------------------------
        // SEED TRANSFERS (20 records)
        // -------------------------------------------------------------
        $transferProviders = ['Blacklane', 'SuperShuttle', 'Careem Executive', 'Lyft Business', 'Uber for Business'];
        $vehicles = [
            'shared_shuttle' => ['name' => 'Ford Transit or similar', 'capacity' => '12 Passengers', 'base_price' => 18.00],
            'private_sedan'  => ['name' => 'Toyota Camry or similar', 'capacity' => '3 Passengers', 'base_price' => 55.00],
            'private_suv'    => ['name' => 'Cadillac Escalade or similar', 'capacity' => '6 Passengers', 'base_price' => 95.00],
            'luxury_van'     => ['name' => 'Mercedes-Benz Sprinter', 'capacity' => '8 Passengers', 'base_price' => 120.00],
        ];
        $transferLocations = [
            ['city' => 'Miami', 'airport' => 'MIA Airport', 'hotel' => 'South Beach Hotel'],
            ['city' => 'New York', 'airport' => 'JFK Airport', 'hotel' => 'Manhattan Hotel'],
            ['city' => 'Los Angeles', 'airport' => 'LAX Airport', 'hotel' => 'Beverly Hills Hotel'],
            ['city' => 'Orlando', 'airport' => 'MCO Airport', 'hotel' => 'Disney Area Resort'],
            ['city' => 'Dubai', 'airport' => 'Dubai Airport', 'hotel' => 'Downtown Hotel'],
        ];

        for ($i = 0; $i < 20; $i++) {
            $provider = $transferProviders[$i % count($transferProviders)];
            $loc = $transferLocations[$i % count($transferLocations)];
            
            // Toggle directions
            $direction = $i % 2 === 0;
            $pickup = $direction ? $loc['airport'] : $loc['hotel'];
            $dropoff = $direction ? $loc['hotel'] : $loc['airport'];

            $typeKeys = array_keys($vehicles);
            $typeKey = $typeKeys[$i % count($typeKeys)];
            $vehicle = $vehicles[$typeKey];

            Transfer::create([
                'transfer_type' => $typeKey,
                'name' => $provider . ' ' . ucwords(str_replace('_', ' ', $typeKey)),
                'vehicle' => $vehicle['name'],
                'capacity' => $vehicle['capacity'],
                'pickup_location' => $pickup,
                'dropoff_location' => $dropoff,
                'price' => $vehicle['base_price'] + ($i * 3.5),
                'currency' => 'USD',
                'is_active' => true,
            ]);
        }
    }
}
