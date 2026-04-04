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
     *     summary="List all properties with pagination",
     *     tags={"Properties"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (default 20, max 100)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of properties with pricing markup applied",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->input('per_page', 20), 100);
            
            $query = PropertyListing::where('is_active', true);
            
            // Filter by location (city/address/name)
            if ($request->has('location')) {
                $query->search($request->location);
            }
            
            // Filter by specific city
            if ($request->has('city')) {
                $query->city($request->city);
            }
            
            if ($request->has('country')) {
                $query->where('country', 'like', "%{$request->country}%");
            }

            // Filter by Price Range
            if ($request->has('minPrice') || $request->has('maxPrice')) {
                $query->priceRange($request->minPrice, $request->maxPrice);
            }
            
            // Filter by Guests (if mapping allows, currently naive max filter or ignore)
            // if ($request->has('guests')) { ... }

            // Standard sorting
            $query->orderBy('created_at', 'desc');
            
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
        $validator = \Validator::make($request->all(), [
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'rooms' => 'nullable|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            $property = PropertyListing::findOrFail($id);
            
            $availabilityData = [
                'property_id' => $property->provider_property_id,
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'adults' => $request->adults,
                'children' => $request->children ?? 0,
                'rooms' => $request->rooms ?? 1,
            ];
            
            // Call OwnerRez channel API
            $result = $this->ownerrezService->checkAvailability($availabilityData);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Check availability error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability'
            ], 500);
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
        $validator = \Validator::make($request->all(), [
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
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
