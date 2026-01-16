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
    
    public function __construct()
    {
        $this->apiKey = config('services.hotelbeds.api_key');
        $this->secret = config('services.hotelbeds.secret');
        $this->baseUrl = config('services.hotelbeds.base_url');
    }
    
    /**
     * Generate API signature
     */
   private function generateSignature()
{
    $timestamp = round(microtime(true) * 1000); // milliseconds
    $signature = hash_hmac('sha256', $timestamp . $this->apiKey, $this->secret);

    return [
        'Api-Key' => $this->apiKey,
        'X-Signature' => $signature,
        'X-Time' => $timestamp,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

    
    /**
     * Search hotels by destination
     * 
     * @param array $params Search parameters
     * @return array
     */
    public function searchHotels(array $params)
    {
        try {
            $cacheKey = 'hotelbeds_search_' . md5(json_encode($params));
            
            // Cache for 1 hour
            return Cache::remember($cacheKey, 3600, function () use ($params) {
                $response = Http::withHeaders($this->generateSignature())
                    ->timeout(30)
                    ->post("{$this->baseUrl}/hotel-api/1.0/hotels", [
                        'stay' => [
                            'checkIn' => $params['checkIn'],
                            'checkOut' => $params['checkOut']
                        ],
                        'occupancies' => $params['occupancies'] ?? [
                            [
                                'rooms' => 1,
                                'adults' => 2,
                                'children' => 0
                            ]
                        ],
                        'destination' => [
                            'code' => $params['destinationCode']
                        ]
                    ]);
                
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json()
                    ];
                }
                
                Log::error('Hotelbeds API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to search hotels',
                    'error' => $response->json()
                ];
            });
        } catch (\Exception $e) {
            Log::error('Hotelbeds Search Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while searching hotels',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get hotel details by code
     * 
     * @param string $hotelCode Hotel code
     * @return array
     */
    public function getHotelDetails(string $hotelCode)
    {
        try {
            $cacheKey = "hotelbeds_hotel_{$hotelCode}";
            
            return Cache::remember($cacheKey, 86400, function () use ($hotelCode) {
                $response = Http::withHeaders($this->generateSignature())
                    ->timeout(30)
                    ->get("{$this->baseUrl}/hotel-content-api/1.0/hotels/{$hotelCode}/details");
                
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json()
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Failed to get hotel details',
                    'error' => $response->json()
                ];
            });
        } catch (\Exception $e) {
            Log::error('Hotelbeds Hotel Details Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while fetching hotel details',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check hotel availability
     * 
     * @param array $params Availability parameters
     * @return array
     */
    public function checkAvailability(array $params)
    {
        try {
            $response = Http::withHeaders($this->generateSignature())
                ->timeout(30)
                ->post("{$this->baseUrl}/hotel-api/1.0/checkrates", [
                    'rooms' => $params['rooms'],
                    'upselling' => $params['upselling'] ?? false
                ]);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to check availability',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds Check Availability Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while checking availability',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a booking
     * 
     * @param array $bookingData Booking information
     * @return array
     */
    public function createBooking(array $bookingData)
    {
        try {
            $response = Http::withHeaders($this->generateSignature())
                ->timeout(30)
                ->post("{$this->baseUrl}/hotel-api/1.0/bookings", $bookingData);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds Create Booking Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while creating booking',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get booking details
     * 
     * @param string $bookingReference Booking reference
     * @return array
     */
    public function getBooking(string $bookingReference)
    {
        try {
            $response = Http::withHeaders($this->generateSignature())
                ->timeout(30)
                ->get("{$this->baseUrl}/hotel-api/1.0/bookings/{$bookingReference}");
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to get booking details',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds Get Booking Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while fetching booking details',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel a booking
     * 
     * @param string $bookingReference Booking reference
     * @return array
     */
    public function cancelBooking(string $bookingReference)
    {
        try {
            $response = Http::withHeaders($this->generateSignature())
                ->timeout(30)
                ->delete("{$this->baseUrl}/hotel-api/1.0/bookings/{$bookingReference}");
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('Hotelbeds Cancel Booking Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while cancelling booking',
                'error' => $e->getMessage()
            ];
        }
    }
}
