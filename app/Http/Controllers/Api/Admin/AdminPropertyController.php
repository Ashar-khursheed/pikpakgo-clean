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
                // Step 1: Get listing index (IDs only)
                $indexResponse = $this->ownerrezService->getListingIndex();

                if ($indexResponse['success']) {
                    $items = $indexResponse['data']['items'] ?? [];

                    foreach ($items as $item) {
                        $listingId = $item['listingExternalId'] ?? null;
                        if (!$listingId) continue;

                        // Step 2: Fetch full listing details for each ID
                        $detail = $this->ownerrezService->getPropertyDetails($listingId);

                        if ($detail['success'] && !empty($detail['data'])) {
                            $raw = $detail['data'];
                            // Merge index meta (active status) into detail data
                            $raw['listingExternalId'] = $listingId;
                            $raw['active']            = $item['active'] ?? true;
                            $this->updateOrCreateProperty($raw, 'ownerrez');
                            $count++;
                        }
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
     * Helper to save property to DB from OwnerRez listing index item.
     * Real OwnerRez items use listingExternalId (not id/property_id).
     */
    protected function updateOrCreateProperty(array $data, string $provider)
    {
        // Support both real OwnerRez (listingExternalId) and legacy mock (id/property_id)
        $propertyId = $data['listingExternalId'] ?? $data['id'] ?? $data['property_id'] ?? null;

        if (!$propertyId) return;

        // OwnerRez XML detail structure (converted to array via simplexml)
        $address  = $data['location']['address'] ?? [];
        $geoCode  = $data['location']['geoCode']['latLng'] ?? [];
        $unit     = $data['units']['unit'] ?? [];
        // Normalize: single unit is assoc array, multiple would be indexed
        if (isset($unit[0])) $unit = $unit[0];

        // Property name — sandbox textValue is empty array; fall back to externalId
        $nameVal  = $data['adContent']['propertyName']['texts']['text']['textValue'] ?? '';
        $name     = (is_string($nameVal) && $nameVal !== '') ? $nameVal : $propertyId;

        $descVal  = $data['adContent']['description']['texts']['text']['textValue'] ?? '';
        $desc     = is_string($descVal) && $descVal !== '' ? $descVal : null;

        // Images — images.image[].uri
        $imgData  = $data['images']['image'] ?? [];
        if (isset($imgData['uri'])) $imgData = [$imgData]; // single image
        $images   = array_values(array_filter(array_column($imgData, 'uri')));

        // Amenities from unit featureValues
        $fvItems  = $unit['featureValues']['featureValue'] ?? [];
        if (isset($fvItems['unitFeatureName'])) $fvItems = [$fvItems];
        $amenities = array_values(array_filter(array_column($fvItems, 'unitFeatureName')));

        // Property type from unit
        $propType = strtolower(str_replace('PROPERTY_TYPE_', '', $unit['propertyType'] ?? 'vacation_rental'));

        // Bedroom/bathroom count
        $bedroomList  = $unit['bedrooms']['bedroom']  ?? [];
        $bathroomList = $unit['bathrooms']['bathroom'] ?? [];
        $bedrooms     = count(isset($bedroomList[0])  ? $bedroomList  : ($bedroomList  ? [$bedroomList]  : []));
        $bathrooms    = count(isset($bathroomList[0]) ? $bathroomList : ($bathroomList ? [$bathroomList] : []));

        // Currency
        $currency = $unit['unitMonetaryInformation']['currency'] ?? 'USD';

        PropertyListing::updateOrCreate(
            [
                'provider'             => $provider,
                'provider_property_id' => $propertyId,
            ],
            [
                'provider_code'  => $propertyId,
                'name'           => $name,
                'description'    => $desc,
                'property_type'  => $propType,
                'city'           => (string)($address['city']            ?? 'Unknown'),
                'state'          => (string)($address['stateOrProvince'] ?? '') ?: null,
                'country'        => (string)($address['country']         ?? '') ?: null,
                'postal_code'    => (string)($address['postalCode']      ?? '') ?: null,
                'latitude'       => !empty($geoCode['latitude'])  ? (float)$geoCode['latitude']  : null,
                'longitude'      => !empty($geoCode['longitude']) ? (float)$geoCode['longitude'] : null,
                'images'         => $images,
                'featured_image' => $images[0] ?? null,
                'amenities'      => $amenities,
                'price_from'     => null, // Pricing comes from quote endpoint, not listing detail
                'price_currency' => $currency,
                'api_data'       => $data,
                'last_synced_at' => now(),
                'is_active'      => (bool)($data['active'] ?? true),
            ]
        );
    }
}
