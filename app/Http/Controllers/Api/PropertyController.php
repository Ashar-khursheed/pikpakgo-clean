<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyListing;
use App\Services\OwnerRezService;
use App\Services\PricingMarkupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


/**
 * @OA\Tag(
 *     name="Properties",
 *     description="Property details and availability"
 * )
 */
class PropertyController extends Controller
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
     * @OA\Get(
     *     path="/public/properties",
     *     summary="List properties with filters and sorting",
     *     tags={"Properties"},
     *     @OA\Parameter(name="location", in="query", description="Free text search (name, city, address)", @OA\Schema(type="string", example="Miami")),
     *     @OA\Parameter(name="city", in="query", @OA\Schema(type="string", example="Miami")),
     *     @OA\Parameter(name="country", in="query", @OA\Schema(type="string", example="US")),
     *     @OA\Parameter(name="property_type", in="query", @OA\Schema(type="string", example="villa")),
     *     @OA\Parameter(name="provider", in="query", @OA\Schema(type="string", enum={"ownerrez","hotelbeds"})),
     *     @OA\Parameter(name="min_price", in="query", @OA\Schema(type="number", example=100)),
     *     @OA\Parameter(name="max_price", in="query", @OA\Schema(type="number", example=500)),
     *     @OA\Parameter(name="star_rating[]", in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="Filter by one or more star ratings"),
     *     @OA\Parameter(name="min_rating", in="query", @OA\Schema(type="number", example=4.0)),
     *     @OA\Parameter(name="bedrooms", in="query", @OA\Schema(type="integer", example=2)),
     *     @OA\Parameter(name="amenities[]", in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="Filter by amenities (all must match)"),
     *     @OA\Parameter(name="is_featured", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"newest","price_asc","price_desc","rating","popular","most_viewed"})),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Paginated list of properties with markup pricing applied")
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->input('per_page', 20), 100);

            $query = PropertyListing::where('is_active', true);

            // Text search
            if ($request->filled('location')) {
                $query->search($request->location);
            }

            // Filters
            if ($request->filled('city'))         $query->city($request->city);
            if ($request->filled('country'))      $query->where('country', 'like', "%{$request->country}%");
            if ($request->filled('property_type')) $query->where('property_type', $request->property_type);
            if ($request->filled('is_featured'))  $query->where('is_featured', (bool) $request->is_featured);
            if ($request->filled('provider'))     $query->where('provider', $request->provider);

            // Price range
            if ($request->filled('min_price') || $request->filled('max_price')) {
                $query->priceRange($request->min_price, $request->max_price);
            }

            // Star rating (single or array: ?star_rating[]=4&star_rating[]=5)
            if ($request->filled('star_rating')) {
                $ratings = is_array($request->star_rating) ? $request->star_rating : [$request->star_rating];
                $query->whereIn('star_rating', $ratings);
            }

            // Min rating
            if ($request->filled('min_rating')) {
                $query->where('rating_average', '>=', $request->min_rating);
            }

            // Bedrooms filter (stored in api_data — try provider_code prefix match)
            if ($request->filled('bedrooms')) {
                $query->whereRaw("JSON_EXTRACT(api_data, '$.bedrooms') >= ?", [(int) $request->bedrooms]);
            }

            // Amenities filter (all requested amenities must be in the JSON array)
            if ($request->filled('amenities')) {
                $amenities = is_array($request->amenities) ? $request->amenities : explode(',', $request->amenities);
                foreach ($amenities as $amenity) {
                    $query->whereJsonContains('amenities', $amenity);
                }
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'newest');
            match ($sortBy) {
                'price_asc'   => $query->orderBy('price_from', 'asc'),
                'price_desc'  => $query->orderBy('price_from', 'desc'),
                'rating'      => $query->orderBy('rating_average', 'desc'),
                'popular'     => $query->orderBy('booking_count', 'desc'),
                'most_viewed' => $query->orderBy('view_count', 'desc'),
                default       => $query->orderBy('created_at', 'desc'),
            };

            $properties = $query->paginate($perPage);
            
            // Apply markup to each property in the collection
            $properties->getCollection()->transform(function ($property) {
                // Calculate markup for default stay (e.g. 1 night, 2 guests, next week)
                // This is an estimation for display purposes. 
                // Detailed pricing requires specific dates via check-availability endpoint.
                
                $basePrice = $property->price_from ?? 0;
                
                if ($basePrice > 0) {
                    $markupData = $this->pricingService->calculateMarkup([
                        'base_price' => $basePrice,
                        'provider' => $property->provider,
                        'property_type' => $property->property_type,
                        'destination_code' => $property->destination_code,
                        'check_in_date' => now()->addDays(7)->toDateString(), // Assumption for general listing
                    ]);
                    
                    $property->display_price = $markupData['final_price'];
                    $property->markup_applied = true;
                } else {
                    $property->display_price = $basePrice;
                }
                
                return $property;
            });
            
            return response()->json([
                'success' => true,
                'data' => $properties
            ]);
            
        } catch (\Exception $e) {
            Log::error('List properties error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to list properties'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/public/properties/{id}",
     *     summary="Get property details",
     *     tags={"Properties"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Property ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Property details"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function show($id)
    {
        try {
            // Try to find in cached listings first
            $property = PropertyListing::where('id', $id)
                ->orWhere('provider_property_id', $id)
                ->first();
            
            if ($property) {
                // Increment view count
                $property->incrementViewCount();
                
                // If property needs sync, fetch fresh data
                if ($property->needsSync()) {
                    $this->syncPropertyData($property);
                }
                
                return response()->json([
                    'success' => true,
                    'data' => $property
                ]);
            }
            
            // If not found in cache, try to fetch from API
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Get property error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * @OA\Post(
     *     path="/public/properties/{id}/check-availability",
     *     summary="Check property availability",
     *     tags={"Properties"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"check_in","check_out","adults"},
     *         @OA\Property(property="check_in", type="string", format="date", example="2027-01-23"),
     *         @OA\Property(property="check_out", type="string", format="date", example="2027-01-26"),
     *         @OA\Property(property="adults", type="integer", example=2),
     *         @OA\Property(property="children", type="integer", example=0)
     *     )),
     *     @OA\Response(response=200, description="Availability result"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function checkAvailability(Request $request, $id)
    {
        // Accept both check_in / check_in_date
        $merged = [];
        if ($request->has('check_in_date') && !$request->has('check_in')) $merged['check_in']  = $request->check_in_date;
        if ($request->has('check_out_date') && !$request->has('check_out')) $merged['check_out'] = $request->check_out_date;
        if (!empty($merged)) $request->merge($merged);

        $validator = \Validator::make($request->all(), [
            'check_in'  => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults'    => 'nullable|integer|min:1',
            'children'  => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $property = PropertyListing::findOrFail($id);

            // For direct properties, check against our local bookings
            if ($property->provider === 'direct') {
                $conflict = \App\Models\Booking::where('property_code', $property->provider_code)
                    ->whereIn('booking_status', ['confirmed', 'pending'])
                    ->where('check_in_date', '<', $request->check_out)
                    ->where('check_out_date', '>', $request->check_in)
                    ->exists();

                return response()->json([
                    'success'   => true,
                    'available' => !$conflict,
                    'message'   => $conflict ? 'Property not available for selected dates' : 'Property is available',
                ]);
            }

            // For OwnerRez properties
            $result = $this->ownerrezService->checkAvailability(
                $property->provider_property_id,
                [
                    'checkin'  => $request->check_in,
                    'checkout' => $request->check_out,
                    'adults'   => (int)($request->adults ?? 2),
                    'children' => (int)($request->children ?? 0),
                ]
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Check availability error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to check availability'], 500);
        }
    }
    
    /**
     * @OA\Post(
     *     path="/public/properties/{id}/get-pricing",
     *     summary="Get pricing for a property",
     *     tags={"Properties"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"check_in","check_out","adults"},
     *         @OA\Property(property="check_in", type="string", format="date", example="2027-01-23"),
     *         @OA\Property(property="check_out", type="string", format="date", example="2027-01-26"),
     *         @OA\Property(property="adults", type="integer", example=2)
     *     )),
     *     @OA\Response(response=200, description="Pricing details with markup applied"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function getPricing(Request $request, $id)
    {
        // Accept both check_in / check_in_date
        $merged = [];
        if ($request->has('check_in_date') && !$request->has('check_in')) $merged['check_in']  = $request->check_in_date;
        if ($request->has('check_out_date') && !$request->has('check_out')) $merged['check_out'] = $request->check_out_date;
        if (!empty($merged)) $request->merge($merged);

        $validator = \Validator::make($request->all(), [
            'check_in'  => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'adults'    => 'nullable|integer|min:1',
            'children'  => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }
        
        try {
            $property = PropertyListing::findOrFail($id);
            
            // Calculate nights
            $checkIn = \Carbon\Carbon::parse($request->check_in);
            $checkOut = \Carbon\Carbon::parse($request->check_out);
            $nights = $checkIn->diffInDays($checkOut);
            
            // Get base price from property or API
            $basePrice = $property->price_from ?? 100; // Default if not available
            $totalBasePrice = $basePrice * $nights;
            
            // Apply markup
            $pricingData = $this->pricingService->calculateMarkup([
                'base_price' => $totalBasePrice,
                'provider' => $property->provider,
                'property_type' => $property->property_type,
                'destination_code' => $property->destination_code,
                'check_in_date' => $request->check_in,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'nights' => $nights,
                    'price_per_night' => $basePrice,
                    'base_total' => $totalBasePrice,
                    'markup_amount' => $pricingData['markup_amount'],
                    'markup_percentage' => $pricingData['markup_percentage'],
                    'final_total' => $pricingData['final_price'],
                    'currency' => $property->price_currency,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get pricing error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate pricing'
            ], 500);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/public/properties/{id}/reviews",
     *     summary="Get property reviews",
     *     tags={"Properties"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Property rating and reviews"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function getReviews($id)
    {
        try {
            $property = PropertyListing::findOrFail($id);
            
            // For now, return cached rating data
            // In production, fetch from API or separate reviews table
            return response()->json([
                'success' => true,
                'data' => [
                    'rating_average' => $property->rating_average,
                    'rating_count' => $property->rating_count,
                    'rating_breakdown' => $property->rating_breakdown,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/public/properties/{id}/similar",
     *     summary="Get similar properties",
     *     tags={"Properties"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of similar properties"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function getSimilarProperties($id)
    {
        try {
            $property = PropertyListing::findOrFail($id);
            
            $similar = PropertyListing::where('id', '!=', $property->id)
                ->where('is_active', true)
                ->where(function($query) use ($property) {
                    $query->where('city', $property->city)
                          ->orWhere('destination_code', $property->destination_code);
                })
                ->where('property_type', $property->property_type)
                ->when($property->star_rating, function($query) use ($property) {
                    $query->whereBetween('star_rating', [
                        $property->star_rating - 1,
                        $property->star_rating + 1
                    ]);
                })
                ->limit(6)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $similar
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get similar properties'
            ], 500);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/public/properties/featured",
     *     summary="Get featured properties for homepage",
     *     tags={"Properties"},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", example=8)),
     *     @OA\Response(response=200, description="Featured properties list")
     * )
     */
    public function featured(Request $request)
    {
        $limit = min((int) $request->get('limit', 8), 24);

        $properties = Cache::remember("featured_properties_{$limit}", 600, function () use ($limit) {
            return PropertyListing::where('is_active', true)
                ->where('is_featured', true)
                ->orderBy('booking_count', 'desc')
                ->limit($limit)
                ->get();
        });

        return response()->json(['success' => true, 'data' => $properties]);
    }

    /**
     * @OA\Get(
     *     path="/public/properties/new-arrivals",
     *     summary="Get recently added properties",
     *     tags={"Properties"},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", example=8)),
     *     @OA\Response(response=200, description="New properties list")
     * )
     */
    public function newArrivals(Request $request)
    {
        $limit = min((int) $request->get('limit', 8), 24);

        $properties = PropertyListing::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $properties]);
    }

    /**
     * @OA\Get(
     *     path="/public/properties/top-rated",
     *     summary="Get top-rated properties",
     *     tags={"Properties"},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", example=8)),
     *     @OA\Response(response=200, description="Top-rated properties list")
     * )
     */
    public function topRated(Request $request)
    {
        $limit = min((int) $request->get('limit', 8), 24);

        $properties = PropertyListing::where('is_active', true)
            ->whereNotNull('rating_average')
            ->orderBy('rating_average', 'desc')
            ->orderBy('rating_count', 'desc')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $properties]);
    }

    /**
     * @OA\Get(
     *     path="/public/properties/{id}/calendar",
     *     summary="Get availability calendar for a property (monthly view)",
     *     tags={"Properties"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer", example=2027)),
     *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer", example=6)),
     *     @OA\Response(response=200, description="Monthly availability calendar"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function calendar(Request $request, $id)
    {
        $property = PropertyListing::where('id', $id)
            ->orWhere('provider_property_id', $id)
            ->firstOrFail();

        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        // Clamp month
        $month = max(1, min(12, $month));
        $year  = max(now()->year, min(now()->year + 2, $year));

        $cacheKey = "property_calendar_{$property->provider_property_id}_{$year}_{$month}";

        $calendar = Cache::remember($cacheKey, 1800, function () use ($property, $year, $month) {
            $start = \Carbon\Carbon::create($year, $month, 1);
            $end   = $start->copy()->endOfMonth();

            // Build blocked dates from our local bookings DB (works for all providers)
            $bookedDates = \App\Models\Booking::where(function ($q) use ($property) {
                    $q->where('property_code', $property->provider_code)
                      ->orWhere('property_code', $property->provider_property_id);
                })
                ->whereIn('booking_status', ['confirmed', 'pending'])
                ->where('check_out_date', '>=', $start->toDateString())
                ->where('check_in_date',  '<=', $end->toDateString())
                ->get(['check_in_date', 'check_out_date']);

            $blocked = [];
            foreach ($bookedDates as $b) {
                $d = \Carbon\Carbon::parse($b->check_in_date);
                while ($d->lt(\Carbon\Carbon::parse($b->check_out_date))) {
                    $blocked[$d->toDateString()] = 'booked';
                    $d->addDay();
                }
            }

            // Build calendar days
            $days = [];
            $day  = $start->copy();
            while ($day->lte($end)) {
                $date   = $day->toDateString();
                $isPast = $day->lt(now()->startOfDay());
                $days[] = [
                    'date'      => $date,
                    'available' => !$isPast && !isset($blocked[$date]),
                    'status'    => $isPast ? 'past' : ($blocked[$date] ?? 'available'),
                ];
                $day->addDay();
            }

            return [
                'property_id' => $property->provider_code ?? $property->provider_property_id,
                'year'        => $year,
                'month'       => $month,
                'month_name'  => $start->format('F Y'),
                'days'        => $days,
            ];
        });

        return response()->json(['success' => true, 'data' => $calendar]);
    }

    /**
     * @OA\Get(
     *     path="/public/properties/amenities",
     *     summary="Get all distinct amenities available (for filter UI)",
     *     tags={"Properties"},
     *     @OA\Response(response=200, description="Amenities list")
     * )
     */
    public function amenities()
    {
        $amenities = Cache::remember('all_amenities', 3600, function () {
            $rows = PropertyListing::where('is_active', true)
                ->whereNotNull('amenities')
                ->pluck('amenities');

            $all = [];
            foreach ($rows as $list) {
                if (is_array($list)) {
                    $all = array_merge($all, $list);
                }
            }

            return array_values(array_unique(array_filter($all)));
        });

        sort($amenities);
        return response()->json(['success' => true, 'data' => $amenities]);
    }

    /**
     * @OA\Get(
     *     path="/public/properties/types",
     *     summary="Get all distinct property types (for filter UI)",
     *     tags={"Properties"},
     *     @OA\Response(response=200, description="Property types list")
     * )
     */
    public function types()
    {
        $types = Cache::remember('all_property_types', 3600, function () {
            return PropertyListing::where('is_active', true)
                ->whereNotNull('property_type')
                ->distinct()
                ->pluck('property_type')
                ->filter()
                ->values();
        });

        return response()->json(['success' => true, 'data' => $types]);
    }

    /**
     * Sync property data from API
     */
    protected function syncPropertyData(PropertyListing $property)
    {
        try {
            $result = $this->ownerrezService->getPropertyDetails($property->provider_property_id);
            
            if ($result['success'] && isset($result['data'])) {
                $property->update([
                    'api_data' => $result['data'],
                    'last_synced_at' => now(),
                    'next_sync_at' => now()->addHours(12),
                ]);
            }
            
            $property->markAsSynced();
            
        } catch (\Exception $e) {
            Log::error('Sync property error: ' . $e->getMessage());
        }
    }
}
