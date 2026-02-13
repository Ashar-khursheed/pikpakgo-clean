<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OwnerRezService
{
    private $username;
    private $password;
    private $baseUrl;
    
    public function __construct()
    {
        $this->username = config('services.ownerrez.username');
        $this->password = config('services.ownerrez.password');
        $this->baseUrl = config('services.ownerrez.base_url');
    }
    
    /**
     * Get authorization headers
     */
    private function getHeaders()
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$this->username}:{$this->password}"),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }
    
    /**
     * Search properties
     * 
     * @param array $params Search parameters
     * @return array
     */
    public function searchProperties(array $params = [])
    {
        if (env('MOCK_SERVICES', false)) {
            return $this->getMockProperties();
        }

        try {
            $queryParams = array_filter([
                'location' => $params['location'] ?? null,
                'checkin' => $params['checkin'] ?? null,
                'checkout' => $params['checkout'] ?? null,
                'guests' => $params['guests'] ?? null,
                'bedrooms' => $params['bedrooms'] ?? null,
                'bathrooms' => $params['bathrooms'] ?? null,
                'minPrice' => $params['minPrice'] ?? null,
                'maxPrice' => $params['maxPrice'] ?? null,
            ]);
            
            $cacheKey = 'ownerrez_search_' . md5(json_encode($queryParams));
            
            return Cache::remember($cacheKey, 3600, function () use ($queryParams) {
                $url = "{$this->baseUrl}/v2/properties";
                $response = Http::withHeaders($this->getHeaders())
                    ->timeout(30)
                    ->get($url, $queryParams);
                
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json()
                    ];
                }
                
                Log::error('OwnerRez API Error', [
                    'status' => $response->status(),
                    'url' => $url,
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to search properties',
                    'url' => $url,
                    'error' => $response->json() ?? $response->body()
                ];
            });
        } catch (\Exception $e) {
            Log::error('OwnerRez Search Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while searching properties',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get property details
     * 
     * @param string $propertyId Property ID
     * @return array
     */
    public function getPropertyDetails(string $propertyId)
    {
        if (env('MOCK_SERVICES', false)) {
            return $this->getMockPropertyDetails($propertyId);
        }

        try {
            $cacheKey = "ownerrez_property_{$propertyId}";
            
            return Cache::remember($cacheKey, 86400, function () use ($propertyId) {
                $response = Http::withHeaders($this->getHeaders())
                    ->timeout(30)
                    ->get("{$this->baseUrl}/v2/properties/{$propertyId}");
                
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json()
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Failed to get property details',
                    'error' => $response->json() ?? $response->body()
                ];
            });
        } catch (\Exception $e) {
            Log::error('OwnerRez Property Details Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while fetching property details',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check availability for a property
     * 
     * @param string $propertyId Property ID
     * @param array $params Availability parameters
     * @return array
     */
    public function checkAvailability(string $propertyId, array $params)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->get("{$this->baseUrl}/v2/properties/{$propertyId}/availability", [
                    'checkin' => $params['checkin'],
                    'checkout' => $params['checkout'],
                    'guests' => $params['guests'] ?? 2
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
            Log::error('OwnerRez Check Availability Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while checking availability',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get pricing for a property
     * 
     * @param string $propertyId Property ID
     * @param array $params Pricing parameters
     * @return array
     */
    public function getPricing(string $propertyId, array $params)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->get("{$this->baseUrl}/v2/properties/{$propertyId}/pricing", [
                    'checkin' => $params['checkin'],
                    'checkout' => $params['checkout'],
                    'guests' => $params['guests'] ?? 2
                ]);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to get pricing',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('OwnerRez Get Pricing Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while fetching pricing',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a booking/reservation
     * 
     * @param array $bookingData Booking information
     * @return array
     */
    public function createBooking(array $bookingData)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post("{$this->baseUrl}/v2/bookings", $bookingData);
            
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
            Log::error('OwnerRez Create Booking Exception', ['error' => $e->getMessage()]);
            
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
     * @param string $bookingId Booking ID
     * @return array
     */
    public function getBooking(string $bookingId)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->get("{$this->baseUrl}/v2/bookings/{$bookingId}");
            
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
            Log::error('OwnerRez Get Booking Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while fetching booking details',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update a booking
     * 
     * @param string $bookingId Booking ID
     * @param array $updateData Update data
     * @return array
     */
    public function updateBooking(string $bookingId, array $updateData)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->put("{$this->baseUrl}/v2/bookings/{$bookingId}", $updateData);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to update booking',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('OwnerRez Update Booking Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while updating booking',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel a booking
     * 
     * @param string $bookingId Booking ID
     * @return array
     */
    public function cancelBooking(string $bookingId)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->delete("{$this->baseUrl}/v2/bookings/{$bookingId}");
            
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
            Log::error('OwnerRez Cancel Booking Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while cancelling booking',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get property reviews
     * 
     * @param string $propertyId Property ID
     * @return array
     */
    public function getReviews(string $propertyId)
    {
        try {
            $cacheKey = "ownerrez_reviews_{$propertyId}";
            
            return Cache::remember($cacheKey, 3600, function () use ($propertyId) {
                $response = Http::withHeaders($this->getHeaders())
                    ->timeout(30)
                    ->get("{$this->baseUrl}/v2/properties/{$propertyId}/reviews");
                
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json()
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Failed to get reviews',
                    'error' => $response->json()
                ];
            });
        } catch (\Exception $e) {
            Log::error('OwnerRez Get Reviews Exception', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while fetching reviews',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch properties via Channel API (HAXML)
     */
    public function fetchPropertiesWithChannelApi()
    {
        // Requires Advertiser ID (ora...)
        // URL: /haapi/haxml/{advertiserId}/listingindex
        
        $advertiserId = config('services.ownerrez.advertiser_id');
        
        if (!$advertiserId) {
            // Try to discover via index? (Failed in testing)
            // For now, return empty or error
            return [
                'success' => false,
                'message' => 'Advertiser ID (ora...) not configured. Please add OWNERREZ_ADVERTISER_ID to .env'
            ];
        }

        $url = "{$this->baseUrl}/haapi/haxml/{$advertiserId}/listingindex";
        
        try {
             $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'User-Agent' => 'PikPakGo/1.0', // Mandatory
                    'Content-Type' => 'application/xml'
                ])
                ->timeout(60)
                ->get($url);

            if ($response->successful()) {
                // Parse XML response
                $xml = simplexml_load_string($response->body());
                $json = json_encode($xml);
                $data = json_decode($json, true);
                
                return [
                    'success' => true,
                    'data' => $data
                ];
            }
            
            Log::error('OwnerRez Channel API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to fetch properties from Channel API',
                'error' => $response->body()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Channel API Exception',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check Availability via Channel API (OLB)
     */
    public function checkChannelAvailability($params)
    {
        $url = "{$this->baseUrl}/haapi/haolbjson/fastavailability";
        
        // Construct body...
        // This is complex, will implement stub first
        return ['success' => false, 'message' => 'Not implemented yet'];
    }
    
    private function getMockProperties()
    {
        return [
            'success' => true,
            'data' => [
                'properties' => [
                    [
                        'id' => 101,
                        'property_id' => 'mock_101',
                        'name' => 'Luxury Ocean View Apartment',
                        'description' => 'Beautiful apartment with direct ocean views.',
                        'property_type' => 'apartment',
                        'city' => 'Miami',
                        'country' => 'United States',
                        'bedrooms' => 2,
                        'bathrooms' => 2,
                        'guests' => 4,
                        'rate' => 250.00,
                        'currency' => 'USD',
                        'images' => [
                            'https://placehold.co/600x400?text=Ocean+View',
                            'https://placehold.co/600x400?text=Bedroom'
                        ],
                        'amenities' => ['Wifi', 'Pool', 'Gym', 'Kitchen'],
                        'star_rating' => 5
                    ],
                    [
                        'id' => 102,
                        'property_id' => 'mock_102',
                        'name' => 'Downtown Modern Loft',
                        'description' => 'Stylish loft in the heart of the city.',
                        'property_type' => 'loft',
                        'city' => 'Miami',
                        'country' => 'United States',
                        'bedrooms' => 1,
                        'bathrooms' => 1,
                        'guests' => 2,
                        'rate' => 175.00,
                        'currency' => 'USD',
                        'images' => [
                            'https://placehold.co/600x400?text=Modern+Loft'
                        ],
                        'amenities' => ['Wifi', 'Elevator', 'Air Conditioning'],
                        'star_rating' => 4
                    ]
                ]
            ]
        ];
    }
}
