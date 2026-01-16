<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HotelbedsService;
use App\Services\OwnerRezService;
use App\Services\PricingMarkupService;
use App\Models\PropertyListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Public Search",
 *     description="Public hotel and property search endpoints (no authentication required)"
 * )
 */
class SearchController extends Controller
{
    protected $hotelbedsService;
    protected $ownerrezService;
    protected $pricingService;
    
    public function __construct(
        HotelbedsService $hotelbedsService,
        OwnerRezService $ownerrezService,
        PricingMarkupService $pricingService
    ) {
        $this->hotelbedsService = $hotelbedsService;
        $this->ownerrezService = $ownerrezService;
        $this->pricingService = $pricingService;
    }
    
    /**
     * @OA\Post(
     *     path="/public/search/hotels",
     *     summary="Search hotels (PUBLIC - No Auth Required)",
     *     description="Search for available hotels from Hotelbeds API with markup pricing",
     *     tags={"Public Search"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"checkIn", "checkOut", "destination"},
     *             @OA\Property(property="checkIn", type="string", format="date", example="2025-02-15"),
     *             @OA\Property(property="checkOut", type="string", format="date", example="2025-02-17"),
     *             @OA\Property(property="destination", type="string", example="NYC", description="City code or name"),
     *             @OA\Property(property="destinationCode", type="string", example="NYC", description="Optional destination code"),
     *             @OA\Property(property="adults", type="integer", example=2),
     *             @OA\Property(property="children", type="integer", example=0),
     *             @OA\Property(property="rooms", type="integer", example=1),
     *             @OA\Property(
     *                 property="occupancies",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="rooms", type="integer", example=1),
     *                     @OA\Property(property="adults", type="integer", example=2),
     *                     @OA\Property(property="children", type="integer", example=0)
     *                 )
     *             ),
     *             @OA\Property(property="minPrice", type="number", example=50),
     *             @OA\Property(property="maxPrice", type="number", example=500),
     *             @OA\Property(property="starRating", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="sortBy", type="string", enum={"price", "rating", "name"}, example="price")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful search with pricing markup applied",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="hotels", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function searchHotels(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'destination' => 'nullable|string',
            'destinationCode' => 'nullable|string',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'rooms' => 'nullable|integer|min:1',
            'occupancies' => 'nullable|array',
            'occupancies.*.rooms' => 'integer|min:1',
            'occupancies.*.adults' => 'integer|min:1',
            'occupancies.*.children' => 'integer|min:0',
            'minPrice' => 'nullable|numeric|min:0',
            'maxPrice' => 'nullable|numeric|min:0',
            'starRating' => 'nullable|array',
            'starRating.*' => 'integer|min:1|max:5',
            'sortBy' => 'nullable|string|in:price,rating,name,distance'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            // Create cache key
            $cacheKey = 'hotel_search_' . md5(json_encode($request->all()));
            
            // Check cache first (5 minutes)
            $result = Cache::remember($cacheKey, 300, function () use ($request) {
                // Call Hotelbeds API
                $apiResponse = $this->hotelbedsService->searchHotels($request->all());
                
                if (!$apiResponse['success']) {
                    return $apiResponse;
                }
                
                // Apply pricing markup to all hotels
                if (isset($apiResponse['data']['hotels'])) {
                    $apiResponse['data']['hotels'] = array_map(function ($hotel) {
                        return $this->applyPricingMarkup($hotel, 'hotelbeds');
                    }, $apiResponse['data']['hotels']);
                }
                
                return $apiResponse;
            });
            
            // Cache property listings in database for faster subsequent searches
            if ($result['success'] && isset($result['data']['hotels'])) {
                $this->cachePropertyListings($result['data']['hotels'], 'hotelbeds');
            }
            
            return response()->json($result, $result['success'] ? 200 : 500);
            
        } catch (\Exception $e) {
            Log::error('Hotel search error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching hotels',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * @OA\Post(
     *     path="/public/search/properties",
     *     summary="Search vacation rentals (PUBLIC - No Auth Required)",
     *     description="Search for vacation rentals from OwnerRez API with markup pricing",
     *     tags={"Public Search"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"checkIn", "checkOut"},
     *             @OA\Property(property="checkIn", type="string", format="date"),
     *             @OA\Property(property="checkOut", type="string", format="date"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="guests", type="integer"),
     *             @OA\Property(property="bedrooms", type="integer"),
     *             @OA\Property(property="propertyType", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful search")
     * )
     */
    public function searchProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'location' => 'nullable|string',
            'guests' => 'nullable|integer|min:1',
            'bedrooms' => 'nullable|integer|min:1',
            'bathrooms' => 'nullable|integer|min:1',
            'propertyType' => 'nullable|string',
            'minPrice' => 'nullable|numeric|min:0',
            'maxPrice' => 'nullable|numeric|min:0',
            'amenities' => 'nullable|array'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            $cacheKey = 'property_search_' . md5(json_encode($request->all()));
            
            $result = Cache::remember($cacheKey, 300, function () use ($request) {
                // Call OwnerRez API
                $apiResponse = $this->ownerrezService->searchProperties($request->all());
                
                if (!$apiResponse['success']) {
                    return $apiResponse;
                }
                
                // Apply pricing markup to all properties
                if (isset($apiResponse['data']['properties'])) {
                    $apiResponse['data']['properties'] = array_map(function ($property) {
                        return $this->applyPricingMarkup($property, 'ownerrez');
                    }, $apiResponse['data']['properties']);
                }
                
                return $apiResponse;
            });
            
            // Cache property listings
            if ($result['success'] && isset($result['data']['properties'])) {
                $this->cachePropertyListings($result['data']['properties'], 'ownerrez');
            }
            
            return response()->json($result, $result['success'] ? 200 : 500);
            
        } catch (\Exception $e) {
            Log::error('Property search error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching properties',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Get popular destinations with cached properties
     */
    public function getPopularDestinations()
    {
        try {
            $destinations = Cache::remember('popular_destinations', 3600, function () {
                return PropertyListing::select('city', 'country', 'destination_code')
                    ->selectRaw('COUNT(*) as property_count')
                    ->selectRaw('AVG(rating_average) as avg_rating')
                    ->selectRaw('MIN(price_from) as min_price')
                    ->where('is_active', true)
                    ->groupBy('city', 'country', 'destination_code')
                    ->having('property_count', '>', 5)
                    ->orderBy('property_count', 'desc')
                    ->limit(20)
                    ->get();
            });
            
            return response()->json([
                'success' => true,
                'data' => $destinations
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get popular destinations error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching destinations'
            ], 500);
        }
    }
    
    /**
     * Get all available destinations
     */
    public function getDestinations(Request $request)
    {
        try {
            $search = $request->get('search');
            
            $query = PropertyListing::select('city', 'country', 'country_code', 'destination_code')
                ->selectRaw('COUNT(*) as property_count')
                ->where('is_active', true)
                ->groupBy('city', 'country', 'country_code', 'destination_code');
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('city', 'LIKE', "%{$search}%")
                      ->orWhere('country', 'LIKE', "%{$search}%");
                });
            }
            
            $destinations = $query->orderBy('city')
                ->limit(100)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $destinations
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get destinations error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching destinations'
            ], 500);
        }
    }
    
    /**
     * Apply pricing markup to a property/hotel
     */
    protected function applyPricingMarkup(array $listing, string $provider): array
    {
        // Get base price from the listing
        $basePrice = $listing['price'] ?? $listing['rate'] ?? 0;
        
        if ($basePrice <= 0) {
            return $listing;
        }
        
        // Calculate markup using the pricing service
        $markupData = $this->pricingService->calculateMarkup([
            'base_price' => $basePrice,
            'provider' => $provider,
            'property_type' => $listing['property_type'] ?? 'hotel',
            'destination_code' => $listing['destination_code'] ?? null,
            'check_in_date' => $listing['check_in_date'] ?? now()->addDays(7)->toDateString(),
        ]);
        
        // Add pricing information to the listing
        $listing['pricing'] = [
            'base_price' => $basePrice,
            'markup_amount' => $markupData['markup_amount'],
            'markup_percentage' => $markupData['markup_percentage'],
            'final_price' => $markupData['final_price'],
            'currency' => $listing['currency'] ?? 'USD',
            'per_night' => $markupData['final_price'] / max(1, $listing['nights'] ?? 1)
        ];
        
        // Update the main price field with final price
        $listing['price'] = $markupData['final_price'];
        
        return $listing;
    }
    
    /**
     * Cache property listings in database
     */
    protected function cachePropertyListings(array $listings, string $provider): void
    {
        try {
            foreach ($listings as $listing) {
                $propertyId = $listing['hotel_code'] ?? $listing['property_id'] ?? null;
                
                if (!$propertyId) {
                    continue;
                }
                
                PropertyListing::updateOrCreate(
                    [
                        'provider' => $provider,
                        'provider_property_id' => $propertyId
                    ],
                    [
                        'provider_code' => $listing['code'] ?? $propertyId,
                        'name' => $listing['name'] ?? 'Unknown',
                        'description' => $listing['description'] ?? null,
                        'property_type' => $listing['property_type'] ?? 'hotel',
                        'star_rating' => $listing['star_rating'] ?? $listing['category_code'] ?? null,
                        'country' => $listing['country'] ?? null,
                        'country_code' => $listing['country_code'] ?? null,
                        'city' => $listing['city'] ?? null,
                        'destination_code' => $listing['destination_code'] ?? null,
                        'address' => $listing['address'] ?? null,
                        'latitude' => $listing['latitude'] ?? null,
                        'longitude' => $listing['longitude'] ?? null,
                        'images' => $listing['images'] ?? [],
                        'featured_image' => $listing['featured_image'] ?? ($listing['images'][0] ?? null),
                        'amenities' => $listing['amenities'] ?? [],
                        'price_from' => $listing['price'] ?? $listing['rate'] ?? null,
                        'price_currency' => $listing['currency'] ?? 'USD',
                        'rating_average' => $listing['rating'] ?? null,
                        'api_data' => $listing,
                        'last_synced_at' => now(),
                        'is_active' => true
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Cache property listings error: ' . $e->getMessage());
        }
    }
}
