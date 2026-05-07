<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
 *     description="Public search endpoints — no authentication required"
 * )
 */
class SearchController extends Controller
{
    protected $ownerrezService;
    protected $pricingService;
    
    public function __construct(
        OwnerRezService $ownerrezService,
        PricingMarkupService $pricingService
    ) {
        $this->ownerrezService = $ownerrezService;
        $this->pricingService = $pricingService;
    }
    

    
    /**
     * @OA\Post(
     *     path="/public/search/hotels",
     *     summary="Search hotels (PUBLIC - No Auth Required)",
     *     description="Alias of searchProperties — searches vacation rental properties from OwnerRez",
     *     tags={"Public Search"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"checkIn", "checkOut"},
     *             @OA\Property(property="checkIn", type="string", format="date", example="2027-01-23"),
     *             @OA\Property(property="checkOut", type="string", format="date", example="2027-01-25"),
     *             @OA\Property(property="location", type="string", example="Miami"),
     *             @OA\Property(property="guests", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful search"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function searchHotels(Request $request)
    {
        return $this->searchProperties($request);
    }

    /**
     * @OA\Post(
     *     path="/public/search/properties",
     *     summary="Search vacation rentals (PUBLIC - No Auth Required)",
     *     description="Search for vacation rentals from OwnerRez API with markup pricing and advanced filters",
     *     tags={"Public Search"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"checkIn", "checkOut"},
     *             @OA\Property(property="checkIn", type="string", format="date", example="2027-01-23"),
     *             @OA\Property(property="checkOut", type="string", format="date", example="2027-01-25"),
     *             @OA\Property(property="location", type="string", example="Miami"),
     *             @OA\Property(property="guests", type="integer", example=2),
     *             @OA\Property(property="bedrooms", type="integer", example=2, description="Minimum bedrooms"),
     *             @OA\Property(property="bathrooms", type="integer", example=1, description="Minimum bathrooms"),
     *             @OA\Property(property="propertyType", type="string", example="apartment"),
     *             @OA\Property(property="minPrice", type="number", example=50),
     *             @OA\Property(property="maxPrice", type="number", example=500),
     *             @OA\Property(property="amenities", type="array", @OA\Items(type="string"), example={"wifi","pool"}),
     *             @OA\Property(property="instantBook", type="boolean", example=false),
     *             @OA\Property(property="lat", type="number", example=25.7617),
     *             @OA\Property(property="lng", type="number", example=-80.1918),
     *             @OA\Property(property="radius_km", type="number", example=25),
     *             @OA\Property(property="sort", type="string", enum={"price_asc","price_desc","rating","featured"}, example="featured"),
     *             @OA\Property(property="per_page", type="integer", example=12)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful search")
     * )
     */
    public function searchProperties(Request $request)
    {
        // Accept both snake_case and camelCase field names
        $input = $request->all();
        if (isset($input['check_in']) && !isset($input['checkIn']))     $input['checkIn']  = $input['check_in'];
        if (isset($input['check_out']) && !isset($input['checkOut']))   $input['checkOut'] = $input['check_out'];
        if (isset($input['destination']) && !isset($input['location'])) $input['location'] = $input['destination'];
        if (isset($input['adults']) && !isset($input['guests']))        $input['guests']   = $input['adults'];
        $request->merge($input);

        $validator = Validator::make($request->all(), [
            'checkIn'      => 'required|date|after_or_equal:today',
            'checkOut'     => 'required|date|after:checkIn',
            'location'     => 'nullable|string',
            'guests'       => 'nullable|integer|min:1',
            'bedrooms'     => 'nullable|integer|min:1',
            'bathrooms'    => 'nullable|integer|min:1',
            'propertyType' => 'nullable|string',
            'minPrice'     => 'nullable|numeric|min:0',
            'maxPrice'     => 'nullable|numeric|min:0',
            'amenities'    => 'nullable|array',
            'amenities.*'  => 'string',
            'instantBook'  => 'nullable|boolean',
            'lat'          => 'nullable|numeric|between:-90,90',
            'lng'          => 'nullable|numeric|between:-180,180',
            'radius_km'    => 'nullable|numeric|min:1|max:500',
            'sort'         => 'nullable|string|in:price_asc,price_desc,rating,featured',
            'per_page'     => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 400);
        }

        $location  = $request->location;
        $minPrice  = $request->minPrice;
        $maxPrice  = $request->maxPrice;
        $perPage   = (int) ($request->per_page ?? 12);
        $sort      = $request->sort ?? 'featured';

        $query = PropertyListing::where('is_active', true)
            ->select(PropertyListing::LISTING_COLUMNS)

            // Location text search
            ->when($location, fn($q) => $q->where(fn($q2) => $q2
                ->where('city', 'like', "%{$location}%")
                ->orWhere('state', 'like', "%{$location}%")
                ->orWhere('country', 'like', "%{$location}%")
                ->orWhere('name', 'like', "%{$location}%")
            ))

            // Map-based bounding box (lat/lng + radius in km using Haversine)
            ->when($request->lat && $request->lng, function ($q) use ($request) {
                $lat    = (float) $request->lat;
                $lng    = (float) $request->lng;
                $radius = (float) ($request->radius_km ?? 25);
                // Haversine formula via raw SQL
                $q->whereRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                    [$lat, $lng, $lat, $radius]
                )->whereNotNull('latitude')->whereNotNull('longitude');
            })

            // Price range filter is handled after fetching properties to account for markup (display price)

            // Property type
            ->when($request->propertyType, fn($q) => $q->where('property_type', $request->propertyType))

            // Bedrooms filter
            ->when($request->bedrooms, fn($q) => $q->where('bedrooms', '>=', (int) $request->bedrooms))

            // Bathrooms filter
            ->when($request->bathrooms, fn($q) => $q->where('bathrooms', '>=', (int) $request->bathrooms))

            // Guests capacity
            ->when($request->guests, fn($q) => $q->where('max_guests', '>=', (int) $request->guests))

            // Instant book
            ->when($request->instantBook, fn($q) => $q->where('instant_book', true))

            // Amenities filter (each amenity must be present in the JSON column)
            ->when($request->amenities, function ($q) use ($request) {
                foreach ($request->amenities as $amenity) {
                    $q->whereJsonContains('amenities', $amenity);
                }
            });

        // Fetch all matching properties to filter by display price
        $properties = $query->get();
        
        $checkIn = $request->checkIn ?? now()->addDays(7)->toDateString();

        // Apply markup and filter by display price
        $processedProperties = $properties->map(function ($property) use ($checkIn) {
            $basePrice = $property->price_from ?? 0;
            
            if ($basePrice > 0) {
                $markupData = $this->pricingService->calculateMarkup([
                    'base_price'       => $basePrice,
                    'provider'         => $property->provider,
                    'property_type'    => $property->property_type,
                    'destination_code' => $property->destination_code,
                    'check_in_date'    => $checkIn,
                ]);
                
                $property->display_price = $markupData['final_price'];
                $property->markup_applied = true;
            } else {
                $property->display_price = $basePrice;
            }
            
            return $property;
        })->filter(function ($property) use ($minPrice, $maxPrice) {
            if ($minPrice && $property->display_price < $minPrice) return false;
            if ($maxPrice && $property->display_price > $maxPrice) return false;
            return true;
        });

        // Sorting (applied to collection to account for display_price)
        $processedProperties = match ($sort) {
            'price_asc'  => $processedProperties->sortBy('display_price'),
            'price_desc' => $processedProperties->sortByDesc('display_price'),
            'rating'     => $processedProperties->sortByDesc('rating_average'),
            default      => $processedProperties->sortByDesc('is_featured')->sortByDesc('rating_average'),
        };

        $processedProperties = $processedProperties->values();

        // Manual pagination
        $total = $processedProperties->count();
        $currentPage = (int) $request->input('page', 1);
        $paginatedItems = $processedProperties->forPage($currentPage, $perPage)->values();
        
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'data'    => $paginated,
            'filters' => [
                'location'     => $location,
                'checkIn'      => $request->checkIn,
                'checkOut'     => $request->checkOut,
                'guests'       => $request->guests,
                'bedrooms'     => $request->bedrooms,
                'bathrooms'    => $request->bathrooms,
                'propertyType' => $request->propertyType,
                'minPrice'     => $minPrice,
                'maxPrice'     => $maxPrice,
                'amenities'    => $request->amenities,
                'sort'         => $sort,
            ],
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/public/search/popular-destinations",
     *     summary="Get popular destinations (PUBLIC)",
     *     description="Returns top destinations ranked by property count",
     *     tags={"Public Search"},
     *     @OA\Response(
     *         response=200,
     *         description="List of popular destinations",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
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
                    ->having('property_count', '>', 0)
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
     * @OA\Get(
     *     path="/public/search/destinations",
     *     summary="Get all destinations (PUBLIC)",
     *     description="Returns all active destination cities, optionally filtered by search term",
     *     tags={"Public Search"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Filter by city or country name",
     *         @OA\Schema(type="string", example="Miami")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of destinations",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
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
     * @OA\Get(
     *     path="/public/search/autocomplete",
     *     summary="Location/property autocomplete for search box",
     *     tags={"Public Search"},
     *     @OA\Parameter(name="q", in="query", required=true, description="Search query (min 2 chars)", @OA\Schema(type="string", example="mia")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", example=10)),
     *     @OA\Response(response=200, description="Autocomplete suggestions grouped by type")
     * )
     */
    public function autocomplete(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $limit = min((int) $request->get('limit', 10), 20);

        $results = Cache::remember('autocomplete_' . md5($q), 300, function () use ($q, $limit) {
            // Cities
            $cities = PropertyListing::where('is_active', true)
                ->where(function ($qb) use ($q) {
                    $qb->where('city', 'like', "{$q}%")
                       ->orWhere('city', 'like', "%{$q}%");
                })
                ->select('city', 'country', 'country_code')
                ->selectRaw('COUNT(*) as property_count')
                ->groupBy('city', 'country', 'country_code')
                ->orderByRaw('COUNT(*) DESC')
                ->limit((int) ceil($limit * 0.5))
                ->get()
                ->map(fn ($r) => [
                    'type'           => 'city',
                    'label'          => $r->city . ($r->country ? ', ' . $r->country : ''),
                    'value'          => $r->city,
                    'property_count' => $r->property_count,
                    'country_code'   => $r->country_code,
                ]);

            // Properties by name
            $properties = PropertyListing::where('is_active', true)
                ->where('name', 'like', "%{$q}%")
                ->select('id', 'name', 'city', 'country', 'provider_property_id', 'featured_image', 'price_from', 'price_currency')
                ->orderBy('booking_count', 'desc')
                ->limit((int) ceil($limit * 0.5))
                ->get()
                ->map(fn ($p) => [
                    'type'          => 'property',
                    'label'         => $p->name,
                    'value'         => $p->seo_slug,
                    'seo_slug'      => $p->seo_slug,
                    'subtitle'      => $p->city . ($p->country ? ', ' . $p->country : ''),
                    'image'         => $p->featured_image,
                    'price_from'    => $p->price_from,
                    'currency'      => $p->price_currency,
                ]);

            return array_values(array_merge($cities->toArray(), $properties->toArray()));
        });

        return response()->json(['success' => true, 'data' => $results]);
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
