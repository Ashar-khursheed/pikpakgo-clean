<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyListing;
use App\Services\OwnerRezService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Admin - Properties",
 *     description="Admin property management endpoints"
 * )
 */
class AdminPropertyController extends Controller
{
    protected $ownerrezService;

    public function __construct(OwnerRezService $ownerrezService)
    {
        $this->ownerrezService = $ownerrezService;
    }

    /**
     * @OA\Get(
     *     path="/admin/properties",
     *     summary="List all properties (Admin)",
     *     tags={"Admin - Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="provider", in="query", @OA\Schema(type="string", example="ownerrez")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Paginated property list"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = PropertyListing::query();

        if ($request->has('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('provider_property_id', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $properties = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $properties
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/properties/{id}",
     *     summary="Get single property (Admin)",
     *     tags={"Admin - Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Property details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $property = PropertyListing::findOrFail($id);
        return response()->json(['success' => true, 'data' => $property]);
    }

    /**
     * @OA\Post(
     *     path="/admin/properties/sync",
     *     summary="Sync properties from OwnerRez API (Admin)",
     *     tags={"Admin - Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=false, @OA\JsonContent(
     *         @OA\Property(property="provider", type="string", example="ownerrez")
     *     )),
     *     @OA\Response(response=200, description="Sync result with count"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function syncFromAPIs(Request $request)
    {
        $provider = $request->provider ?? 'ownerrez';
        $count = 0;

        try {
            if ($provider === 'ownerrez') {
                // Fetch properties from OwnerRez
                $response = $this->ownerrezService->searchProperties([]); 
                
                if ($response['success']) {
                    // OwnerRez API structure typically returns a list directly or inside 'items'
                    // Based on OwnerRezService::getMockProperties, it returns ['data' => ['properties' => [...]]]
                    // We need to handle both mock and real API responses if they differ
                    // Real OwnerRez V2 /properties usually returns an array of property objects
                    
                    $data = $response['data'];
                    $items = $data['items'] ?? $data['properties'] ?? ($data[0] ? $data : []);

                    foreach ($items as $item) {
                        $this->updateOrCreateProperty($item, 'ownerrez');
                        $count++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$count} properties from {$provider}"
            ]);

        } catch (\Exception $e) {
            Log::error("Sync Error: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * @OA\Put(
     *     path="/admin/properties/{id}/status",
     *     summary="Update property active status (Admin)",
     *     tags={"Admin - Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"is_active"},
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $property = PropertyListing::findOrFail($id);
        
        $request->validate([
            'is_active' => 'required|boolean'
        ]);
        
        $property->update(['is_active' => $request->is_active]);
        
        return response()->json([
            'success' => true,
            'message' => 'Property status updated',
            'data' => $property
        ]);
    }

    /**
     * Helper to save property to DB
     */
    protected function updateOrCreateProperty(array $data, string $provider)
    {
        $propertyId = $data['id'] ?? $data['property_id'] ?? null;
        
        if (!$propertyId) return;

        PropertyListing::updateOrCreate(
            [
                'provider' => $provider,
                'provider_property_id' => $propertyId
            ],
            [
                'name' => $data['name'] ?? 'Unknown Property',
                'description' => $data['description'] ?? null,
                'property_type' => $data['property_type'] ?? 'vacation_rental',
                'city' => $data['address']['city'] ?? $data['city'] ?? null,
                'country' => $data['address']['country'] ?? $data['country'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'images' => $data['images'] ?? [],
                'featured_image' => $data['thumbnail_url'] ?? ($data['images'][0] ?? null),
                'amenities' => $data['amenities'] ?? [],
                'price_from' => $data['rate'] ?? 0, // Base rate if available
                'price_currency' => $data['currency_code'] ?? 'USD',
                'api_data' => $data,
                'last_synced_at' => now(),
                'is_active' => true
            ]
        );
    }
}
