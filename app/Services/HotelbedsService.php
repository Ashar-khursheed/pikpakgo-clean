<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HotelbedsService
{
    private $apiKey;
    private $secret;
    private $baseUrl;
    private $timeout;

    public function __construct()
    {
        $this->apiKey  = config('services.hotelbeds.api_key');
        $this->secret  = config('services.hotelbeds.secret');
        $this->baseUrl = config('services.hotelbeds.base_url', 'https://api.test.hotelbeds.com');
        $this->timeout = 30;
    }

    /**
     * Generate headers required by Hotelbeds APITUDE.
     */
    private function getHeaders(): array
    {
        $timestamp = time();
        $signature = hash("sha256", $this->apiKey . $this->secret . $timestamp);

        return [
            'Api-Key'      => $this->apiKey,
            'X-Signature'  => $signature,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent'   => 'PikPakGo/1.0',
        ];
    }

    /**
     * Search Hotels.
     * Uses Hotelbeds APITUDE Booking API: POST /hotel-api/1.0/hotels
     */
    public function searchHotels(array $params): array
    {
        if (env('MOCK_SERVICES', true) || !$this->apiKey || !$this->secret) {
            return $this->getMockHotelSearch($params);
        }

        try {
            $url = "{$this->baseUrl}/hotel-api/1.0/hotels";

            // Map input parameters to Hotelbeds request format
            // Hotelbeds requires checkIn, checkOut, occupancy (rooms, adults, children)
            $payload = [
                'stay' => [
                    'checkIn'  => $params['checkIn'] ?? $params['check_in'] ?? now()->addDays(7)->toDateString(),
                    'checkOut' => $params['checkOut'] ?? $params['check_out'] ?? now()->addDays(9)->toDateString(),
                ],
                'occupancies' => [
                    [
                        'rooms'    => 1,
                        'adults'   => (int)($params['guests'] ?? $params['adults'] ?? 2),
                        'children' => (int)($params['children'] ?? 0),
                    ]
                ],
            ];

            // If a specific destination code is provided (e.g. NYC, MIA)
            if (!empty($params['destinationCode'])) {
                $payload['destination'] = ['code' => $params['destinationCode']];
            } elseif (!empty($params['location'])) {
                // In a production system, we'd map destination text to code. 
                // For test/fallback, we search by a destination code matching location prefix or default.
                $payload['destination'] = ['code' => strtoupper(substr($params['location'], 0, 3))];
            }

            // Filter by hotel codes if specified
            if (!empty($params['hotelCodes'])) {
                $payload['hotels'] = ['hotel' => is_array($params['hotelCodes']) ? $params['hotelCodes'] : [$params['hotelCodes']]];
            }

            $response = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }

            Log::error('Hotelbeds searchHotels error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to search hotels from Hotelbeds',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds searchHotels exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get Hotel Details (Content API).
     * Uses Hotelbeds APITUDE Content API: GET /hotel-content-api/3.0/hotels/{hotelCode}/details
     */
    public function getHotelDetails(string $hotelCode, string $language = 'ENG'): array
    {
        if (env('MOCK_SERVICES', true) || !$this->apiKey || !$this->secret) {
            return $this->getMockHotelDetails($hotelCode);
        }

        try {
            return Cache::remember("hotelbeds_details_{$hotelCode}_{$language}", 86400, function () use ($hotelCode, $language) {
                $url = "{$this->baseUrl}/hotel-content-api/3.0/hotels/{$hotelCode}/details?language={$language}";

                $response = Http::withHeaders($this->getHeaders())
                    ->timeout($this->timeout)
                    ->get($url);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data'    => $response->json(),
                    ];
                }

                Log::error('Hotelbeds getHotelDetails error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to fetch hotel details from Hotelbeds',
                    'status'  => $response->status(),
                    'error'   => $response->body(),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Hotelbeds getHotelDetails exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check Room Availability & Rates.
     * Re-checks rates for a specific hotel before booking.
     */
    public function checkAvailability(string $hotelCode, array $params): array
    {
        if (env('MOCK_SERVICES', true) || !$this->apiKey || !$this->secret) {
            return [
                'success' => true,
                'available' => true,
                'data' => [
                    'hotel' => [
                        'code' => $hotelCode,
                        'name' => 'Mock Hotelbeds Property',
                        'rooms' => [
                            [
                                'code' => 'DBL.ST',
                                'name' => 'Double Standard Room',
                                'rates' => [
                                    [
                                        'rateKey' => 'mock-rate-key-12345',
                                        'net' => 150.00,
                                        'sellingRate' => 175.00,
                                        'boardingName' => 'ROOM ONLY',
                                        'cancellationPolicies' => [
                                            [
                                                'amount' => 150.00,
                                                'from' => now()->addDays(2)->toIso8601String()
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        try {
            $url = "{$this->baseUrl}/hotel-api/1.0/hotels";

            $payload = [
                'stay' => [
                    'checkIn'  => $params['check_in'] ?? $params['checkIn'] ?? now()->addDays(7)->toDateString(),
                    'checkOut' => $params['check_out'] ?? $params['checkOut'] ?? now()->addDays(9)->toDateString(),
                ],
                'occupancies' => [
                    [
                        'rooms'    => 1,
                        'adults'   => (int)($params['adults'] ?? 2),
                        'children' => (int)($params['children'] ?? 0),
                    ]
                ],
                'hotels' => [
                    'hotel' => [(int)$hotelCode]
                ]
            ];

            $response = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $hotels = $data['hotels']['hotels'] ?? [];
                $available = count($hotels) > 0;

                return [
                    'success'   => true,
                    'available' => $available,
                    'data'      => $available ? $hotels[0] : null,
                ];
            }

            Log::error('Hotelbeds checkAvailability error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to check hotel room availability',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds checkAvailability exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create Hotel Booking.
     * Uses Hotelbeds APITUDE Booking API: POST /hotel-api/1.0/bookings
     */
    public function createBooking(array $bookingData): array
    {
        if (env('MOCK_SERVICES', true) || !$this->apiKey || !$this->secret) {
            return [
                'success' => true,
                'data' => [
                    'booking' => [
                        'reference' => 'HB-' . strtoupper(str_replace('.', '', uniqid('', true))),
                        'status' => 'CONFIRMED',
                        'totalNet' => $bookingData['totalAmount'] ?? 150.00,
                        'currency' => $bookingData['currency'] ?? 'USD',
                        'hotel' => [
                            'code' => $bookingData['hotelCode'] ?? '123',
                            'name' => $bookingData['hotelName'] ?? 'Mock Hotelbeds Property',
                        ]
                    ]
                ]
            ];
        }

        try {
            $url = "{$this->baseUrl}/hotel-api/1.0/bookings";

            // Format check for booking request
            $payload = [
                'holder' => [
                    'name'    => $bookingData['traveler']['firstName'] ?? 'Guest',
                    'surname' => $bookingData['traveler']['lastName'] ?? 'User',
                ],
                'rooms' => [
                    [
                        'rateKey' => $bookingData['rateKey'],
                        'paxes'   => []
                    ]
                ],
                'clientReference' => $bookingData['clientReference'] ?? 'PKG-' . uniqid()
            ];

            // Add Pax details if provided
            $guests = $bookingData['guestsList'] ?? [];
            if (!empty($guests)) {
                foreach ($guests as $index => $g) {
                    $payload['rooms'][0]['paxes'][] = [
                        'roomId'  => 1,
                        'type'    => $g['type'] ?? 'AD', // AD = Adult, CH = Child
                        'name'    => $g['firstName'] ?? 'Guest',
                        'surname' => $g['lastName'] ?? 'User ' . ($index + 1),
                        'age'     => $g['age'] ?? 30,
                    ];
                }
            } else {
                // Fallback default pax
                $payload['rooms'][0]['paxes'][] = [
                    'roomId'  => 1,
                    'type'    => 'AD',
                    'name'    => $bookingData['traveler']['firstName'] ?? 'Guest',
                    'surname' => $bookingData['traveler']['lastName'] ?? 'User',
                ];
            }

            $response = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }

            Log::error('Hotelbeds createBooking error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create booking on Hotelbeds',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds createBooking exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get Booking Details.
     * Uses Hotelbeds APITUDE Booking API: GET /hotel-api/1.0/bookings/{bookingReference}
     */
    public function getBooking(string $bookingReference): array
    {
        if (env('MOCK_SERVICES', true) || !$this->apiKey || !$this->secret) {
            return [
                'success' => true,
                'data' => [
                    'booking' => [
                        'reference' => $bookingReference,
                        'status' => 'CONFIRMED',
                        'creationDate' => now()->toDateString(),
                        'totalNet' => 150.00,
                        'currency' => 'USD',
                    ]
                ]
            ];
        }

        try {
            $url = "{$this->baseUrl}/hotel-api/1.0/bookings/{$bookingReference}";

            $response = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }

            Log::error('Hotelbeds getBooking error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve booking from Hotelbeds',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds getBooking exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cancel Booking.
     * Uses Hotelbeds APITUDE Booking API: DELETE /hotel-api/1.0/bookings/{bookingReference}
     */
    public function cancelBooking(string $bookingReference): array
    {
        if (env('MOCK_SERVICES', true) || !$this->apiKey || !$this->secret) {
            return [
                'success' => true,
                'message' => 'Booking cancelled successfully (Mock)',
            ];
        }

        try {
            $url = "{$this->baseUrl}/hotel-api/1.0/bookings/{$bookingReference}?cancellationFlag=CANCELLATION";

            $response = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->delete($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }

            Log::error('Hotelbeds cancelBooking error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to cancel booking on Hotelbeds',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds cancelBooking exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Mock helpers
    // ------------------------------------------------------------------

    private function getMockHotelSearch(array $params): array
    {
        $checkIn = $params['checkIn'] ?? $params['check_in'] ?? now()->addDays(7)->toDateString();
        $checkOut = $params['checkOut'] ?? $params['check_out'] ?? now()->addDays(9)->toDateString();
        
        return [
            'success' => true,
            'data' => [
                'hotels' => [
                    'hotels' => [
                        [
                            'code' => 1001,
                            'name' => 'Stardust Resort & Spa',
                            'categoryCode' => '4EST',
                            'categoryName' => '4 STARS',
                            'destinationCode' => 'MIA',
                            'destinationName' => 'Miami',
                            'latitude' => 25.79065,
                            'longitude' => -80.130045,
                            'minRate' => 120.00,
                            'maxRate' => 300.00,
                            'currency' => 'USD',
                            'rooms' => [
                                [
                                    'code' => 'DBL.ST',
                                    'name' => 'Double Standard',
                                    'rates' => [
                                        [
                                            'rateKey' => 'rate-key-stardust-dbl',
                                            'rateClass' => 'NOR',
                                            'rateType' => 'BOOKABLE',
                                            'net' => 120.00,
                                            'sellingRate' => 140.00,
                                            'boardCode' => 'RO',
                                            'boardName' => 'ROOM ONLY',
                                            'allotment' => 5,
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'code' => 1002,
                            'name' => 'Grand Plaza Hotel',
                            'categoryCode' => '5EST',
                            'categoryName' => '5 STARS',
                            'destinationCode' => 'NYC',
                            'destinationName' => 'New York',
                            'latitude' => 40.758895,
                            'longitude' => -73.985131,
                            'minRate' => 210.00,
                            'maxRate' => 550.00,
                            'currency' => 'USD',
                            'rooms' => [
                                [
                                    'code' => 'SNG.DLX',
                                    'name' => 'Single Deluxe',
                                    'rates' => [
                                        [
                                            'rateKey' => 'rate-key-grandplaza-sng',
                                            'rateClass' => 'NOR',
                                            'rateType' => 'BOOKABLE',
                                            'net' => 210.00,
                                            'sellingRate' => 245.00,
                                            'boardCode' => 'BB',
                                            'boardName' => 'BED & BREAKFAST',
                                            'allotment' => 2,
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'checkIn' => $checkIn,
                    'checkOut' => $checkOut,
                    'total' => 2
                ]
            ]
        ];
    }

    private function getMockHotelDetails(string $hotelCode): array
    {
        $hotels = [
            '1001' => [
                'code' => 1001,
                'name' => 'Stardust Resort & Spa',
                'description' => 'A wonderful beachfront resort in Miami offering luxury rooms, standard spa facilities, and a private pool.',
                'address' => '1001 Ocean Drive, Miami Beach, FL 33139',
                'phones' => ['+1 305-555-0199'],
                'email' => 'info@stardustresort.com',
                'categoryCode' => '4EST',
                'categoryName' => '4 STARS',
                'latitude' => 25.79065,
                'longitude' => -80.130045,
                'images' => [
                    ['path' => 'https://placehold.co/800x600?text=Stardust+Exterior', 'type' => 'HABITACION'],
                    ['path' => 'https://placehold.co/800x600?text=Stardust+Room', 'type' => 'GENERAL']
                ],
                'amenities' => ['Wifi', 'Pool', 'Spa', 'Beach Access', 'Gym', 'Restaurant']
            ],
            '1002' => [
                'code' => 1002,
                'name' => 'Grand Plaza Hotel',
                'description' => 'Superb luxury hotel located in Times Square, New York. Offers skybar, premium fitness center, and high-speed fiber internet.',
                'address' => '1515 Broadway, New York, NY 10036',
                'phones' => ['+1 212-555-0100'],
                'email' => 'stay@grandplazanyc.com',
                'categoryCode' => '5EST',
                'categoryName' => '5 STARS',
                'latitude' => 40.758895,
                'longitude' => -73.985131,
                'images' => [
                    ['path' => 'https://placehold.co/800x600?text=Grand+Plaza+Lobby', 'type' => 'GENERAL'],
                    ['path' => 'https://placehold.co/800x600?text=Grand+Plaza+Suite', 'type' => 'HABITACION']
                ],
                'amenities' => ['Wifi', 'Room Service', 'Bar', 'Gym', 'Business Center', 'Concierge']
            ]
        ];

        $details = $hotels[$hotelCode] ?? [
            'code' => (int)$hotelCode,
            'name' => 'Hotelbeds Property #' . $hotelCode,
            'description' => 'A comfortable and pleasant accommodation matching your query.',
            'address' => 'Main Street, Destination City',
            'phones' => ['+1 800-555-0100'],
            'email' => 'contact@hotelbeds-property.com',
            'categoryCode' => '3EST',
            'categoryName' => '3 STARS',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'images' => [
                ['path' => 'https://placehold.co/800x600?text=Hotelbeds+Property', 'type' => 'GENERAL']
            ],
            'amenities' => ['Wifi', 'Air Conditioning']
        ];

        return [
            'success' => true,
            'data' => $details
        ];
    }
}
