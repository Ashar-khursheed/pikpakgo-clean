<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyListing;
use App\Services\HotelbedsService;
use App\Services\OwnerRezService;
use App\Services\PricingMarkupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PropertyController extends Controller
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
     * Get property details by ID
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
     * Check property availability
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
            
            // Call appropriate API based on provider
            if ($property->provider === 'hotelbeds') {
                $result = $this->hotelbedsService->checkAvailability($availabilityData);
            } else {
                $result = $this->ownerrezService->checkAvailability($availabilityData);
            }
            
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
     * Get pricing for property
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
     * Get property reviews
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
     * Get similar properties
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
            if ($property->provider === 'hotelbeds') {
                $result = $this->hotelbedsService->getHotelDetails($property->provider_property_id);
            } else {
                $result = $this->ownerrezService->getPropertyDetails($property->provider_property_id);
            }
            
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
