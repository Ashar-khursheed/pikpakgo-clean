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

        $properties = $query->with('seoConfig')->orderBy('created_at', 'desc')->paginate(20);

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
        $property = PropertyListing::with('seoConfig')->findOrFail($id);
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
     * @OA\Post(
     *     path="/admin/properties",
     *     summary="Create a property manually (Admin)",
     *     tags={"Admin - Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name","provider","property_type","city","country"},
     *         @OA\Property(property="name",                type="string",  example="Ocean View Villa"),
     *         @OA\Property(property="provider",            type="string",  enum={"ownerrez","hotelbeds","direct"}, example="direct"),
     *         @OA\Property(property="provider_property_id",type="string",  example="CUSTOM-001"),
     *         @OA\Property(property="provider_code",       type="string",  example="CUSTOM-001"),
     *         @OA\Property(property="description",         type="string"),
     *         @OA\Property(property="property_type",       type="string",  example="villa"),
     *         @OA\Property(property="category",            type="string",  enum={"budget","standard","superior","luxury"}),
     *         @OA\Property(property="star_rating",         type="integer", example=4),
     *         @OA\Property(property="country",             type="string",  example="United States"),
     *         @OA\Property(property="country_code",        type="string",  example="US"),
     *         @OA\Property(property="state",               type="string",  example="Florida"),
     *         @OA\Property(property="city",                type="string",  example="Miami"),
     *         @OA\Property(property="address",             type="string"),
     *         @OA\Property(property="postal_code",         type="string"),
     *         @OA\Property(property="latitude",            type="number",  format="float", example=25.7617),
     *         @OA\Property(property="longitude",           type="number",  format="float", example=-80.1918),
     *         @OA\Property(property="phone",               type="string"),
     *         @OA\Property(property="email",               type="string",  format="email"),
     *         @OA\Property(property="website",             type="string"),
     *         @OA\Property(property="images",              type="array",   @OA\Items(type="string")),
     *         @OA\Property(property="featured_image",      type="string"),
     *         @OA\Property(property="amenities",           type="array",   @OA\Items(type="string")),
     *         @OA\Property(property="total_rooms",         type="integer"),
     *         @OA\Property(property="price_from",          type="number",  example=150.00),
     *         @OA\Property(property="price_currency",      type="string",  example="USD"),
     *         @OA\Property(property="check_in_time",       type="string",  example="15:00"),
     *         @OA\Property(property="check_out_time",      type="string",  example="11:00"),
     *         @OA\Property(property="cancellation_policy", type="string"),
     *         @OA\Property(property="child_policy",        type="string"),
     *         @OA\Property(property="pet_policy",          type="string"),
     *         @OA\Property(property="is_active",           type="boolean", example=true),
     *         @OA\Property(property="is_featured",         type="boolean", example=false),
     *         @OA\Property(property="meta_title",          type="string",  example="Custom SEO Title"),
     *         @OA\Property(property="meta_description",    type="string",  example="Custom SEO description..."),
     *         @OA\Property(property="seo_slug",            type="string",  example="luxury-miami-villa"),
     *         @OA\Property(property="og_title",            type="string"),
     *         @OA\Property(property="og_description",      type="string"),
     *         @OA\Property(property="og_image",            type="string",  format="binary"),
     *         @OA\Property(property="twitter_title",       type="string"),
     *         @OA\Property(property="twitter_description", type="string"),
     *         @OA\Property(property="twitter_image",       type="string",  format="binary"),
     *         @OA\Property(property="canonical_url",       type="string"),
     *         @OA\Property(property="no_index",            type="boolean"),
     *         @OA\Property(property="no_follow",           type="boolean"),
     *         @OA\Property(property="schema_markup",       type="object")
     *     )),
     *     @OA\Response(response=201, description="Property created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'provider'             => 'required|in:ownerrez,hotelbeds,direct',
            'provider_property_id' => 'nullable|string|max:255',
            'provider_code'        => 'nullable|string|max:255',
            'description'          => 'nullable|string',
            'property_type'        => 'required|string|max:100',
            'category'             => 'nullable|in:budget,standard,superior,luxury',
            'star_rating'          => 'nullable|integer|min:1|max:5',
            'country'              => 'required|string|max:100',
            'country_code'         => 'nullable|string|max:3',
            'state'                => 'nullable|string|max:100',
            'city'                 => 'required|string|max:100',
            'address'              => 'nullable|string|max:500',
            'postal_code'          => 'nullable|string|max:20',
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
            'phone'                => 'nullable|string|max:30',
            'email'                => 'nullable|email|max:200',
            'website'              => 'nullable|url|max:255',
            'images'               => 'nullable|array',
            'images.*'             => 'string',
            'featured_image'       => 'nullable|string',
            'amenities'            => 'nullable|array',
            'amenities.*'          => 'string',
            'total_rooms'          => 'nullable|integer|min:1',
            'price_from'           => 'nullable|numeric|min:0',
            'price_currency'       => 'nullable|string|max:3',
            'check_in_time'        => 'nullable|date_format:H:i',
            'check_out_time'       => 'nullable|date_format:H:i',
            'cancellation_policy'  => 'nullable|string',
            'child_policy'         => 'nullable|string',
            'pet_policy'           => 'nullable|string',
            'is_active'            => 'boolean',
            'is_featured'          => 'boolean',
            'meta_title'           => 'nullable|string|max:255',
            'meta_description'     => 'nullable|string|max:500',
            'seo_slug'             => 'nullable|string|max:255',
            'og_title'             => 'nullable|string|max:255',
            'og_description'       => 'nullable|string|max:500',
            'og_image'             => 'nullable', // can be file or string
            'twitter_title'        => 'nullable|string|max:255',
            'twitter_description'  => 'nullable|string|max:500',
            'twitter_image'        => 'nullable', // can be file or string
            'canonical_url'        => 'nullable|url|max:255',
            'no_index'             => 'nullable',
            'no_follow'            => 'nullable',
            'schema_markup'        => 'nullable|array',
        ]);

        $property = PropertyListing::create($validated);

        $this->syncSeoData($property, $request);

        return response()->json(['success' => true, 'data' => $property], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/properties/{id}",
     *     summary="Update a property manually (Admin)",
     *     tags={"Admin - Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name",                type="string"),
     *         @OA\Property(property="description",         type="string"),
     *         @OA\Property(property="property_type",       type="string"),
     *         @OA\Property(property="category",            type="string",  enum={"budget","standard","superior","luxury"}),
     *         @OA\Property(property="star_rating",         type="integer"),
     *         @OA\Property(property="country",             type="string"),
     *         @OA\Property(property="country_code",        type="string"),
     *         @OA\Property(property="state",               type="string"),
     *         @OA\Property(property="city",                type="string"),
     *         @OA\Property(property="address",             type="string"),
     *         @OA\Property(property="postal_code",         type="string"),
     *         @OA\Property(property="latitude",            type="number",  format="float"),
     *         @OA\Property(property="longitude",           type="number",  format="float"),
     *         @OA\Property(property="phone",               type="string"),
     *         @OA\Property(property="email",               type="string",  format="email"),
     *         @OA\Property(property="website",             type="string"),
     *         @OA\Property(property="images",              type="array",   @OA\Items(type="string")),
     *         @OA\Property(property="featured_image",      type="string"),
     *         @OA\Property(property="amenities",           type="array",   @OA\Items(type="string")),
     *         @OA\Property(property="total_rooms",         type="integer"),
     *         @OA\Property(property="price_from",          type="number"),
     *         @OA\Property(property="price_currency",      type="string"),
     *         @OA\Property(property="check_in_time",       type="string",  example="15:00"),
     *         @OA\Property(property="check_out_time",      type="string",  example="11:00"),
     *         @OA\Property(property="cancellation_policy", type="string"),
     *         @OA\Property(property="child_policy",        type="string"),
     *         @OA\Property(property="pet_policy",          type="string"),
     *         @OA\Property(property="is_active",           type="boolean"),
     *         @OA\Property(property="is_featured",         type="boolean"),
     *         @OA\Property(property="meta_title",          type="string"),
     *         @OA\Property(property="meta_description",    type="string"),
     *         @OA\Property(property="seo_slug",            type="string"),
     *         @OA\Property(property="og_title",            type="string"),
     *         @OA\Property(property="og_description",      type="string"),
     *         @OA\Property(property="og_image",            type="string",  format="binary"),
     *         @OA\Property(property="twitter_title",       type="string"),
     *         @OA\Property(property="twitter_description", type="string"),
     *         @OA\Property(property="twitter_image",       type="string",  format="binary"),
     *         @OA\Property(property="canonical_url",       type="string"),
     *         @OA\Property(property="no_index",            type="boolean"),
     *         @OA\Property(property="no_follow",           type="boolean"),
     *         @OA\Property(property="schema_markup",       type="object")
     *     )),
     *     @OA\Response(response=200, description="Property updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $property = PropertyListing::findOrFail($id);

        $validated = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'description'         => 'sometimes|nullable|string',
            'property_type'       => 'sometimes|string|max:100',
            'category'            => 'sometimes|nullable|in:budget,standard,superior,luxury',
            'star_rating'         => 'sometimes|nullable|integer|min:1|max:5',
            'country'             => 'sometimes|string|max:100',
            'country_code'        => 'sometimes|nullable|string|max:3',
            'state'               => 'sometimes|nullable|string|max:100',
            'city'                => 'sometimes|string|max:100',
            'address'             => 'sometimes|nullable|string|max:500',
            'postal_code'         => 'sometimes|nullable|string|max:20',
            'latitude'            => 'sometimes|nullable|numeric|between:-90,90',
            'longitude'           => 'sometimes|nullable|numeric|between:-180,180',
            'phone'               => 'sometimes|nullable|string|max:30',
            'email'               => 'sometimes|nullable|email|max:200',
            'website'             => 'sometimes|nullable|url|max:255',
            'images'              => 'sometimes|nullable|array',
            'images.*'            => 'string',
            'featured_image'      => 'sometimes|nullable|string',
            'amenities'           => 'sometimes|nullable|array',
            'amenities.*'         => 'string',
            'total_rooms'         => 'sometimes|nullable|integer|min:1',
            'price_from'          => 'sometimes|nullable|numeric|min:0',
            'price_currency'      => 'sometimes|nullable|string|max:3',
            'check_in_time'       => 'sometimes|nullable|date_format:H:i',
            'check_out_time'      => 'sometimes|nullable|date_format:H:i',
            'cancellation_policy' => 'sometimes|nullable|string',
            'child_policy'        => 'sometimes|nullable|string',
            'pet_policy'          => 'sometimes|nullable|string',
            'is_active'           => 'sometimes|boolean',
            'is_featured'         => 'sometimes|boolean',
            'meta_title'          => 'nullable|string|max:255',
            'meta_description'    => 'nullable|string|max:500',
            'seo_slug'            => 'nullable|string|max:255',
            'og_title'            => 'nullable|string|max:255',
            'og_description'      => 'nullable|string|max:500',
            'og_image'            => 'nullable',
            'twitter_title'       => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string|max:500',
            'twitter_image'       => 'nullable',
            'canonical_url'       => 'nullable|url|max:255',
            'no_index'            => 'nullable',
            'no_follow'           => 'nullable',
            'schema_markup'       => 'nullable|array',
        ]);

        $property->update($validated);

        $this->syncSeoData($property, $request);

        return response()->json(['success' => true, 'message' => 'Property updated', 'data' => $property->fresh()]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/properties/{id}",
     *     summary="Delete a property (Admin)",
     *     tags={"Admin - Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Property deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $property = PropertyListing::findOrFail($id);
        $property->delete();

        return response()->json(['success' => true, 'message' => 'Property deleted successfully']);
    }

    /**
     * Sync SEO data for a property
     */
    protected function syncSeoData($property, Request $request)
    {
        // Only sync if at least one SEO field is present
        $seoFields = [
            'meta_title', 'meta_description', 'seo_slug', 
            'og_title', 'og_description', 'og_image',
            'twitter_title', 'twitter_description', 'twitter_image',
            'canonical_url', 'no_index', 'no_follow', 'schema_markup'
        ];

        $hasSeo = false;
        foreach ($seoFields as $field) {
            if ($request->has($field) || $request->hasFile($field)) {
                $hasSeo = true;
                break;
            }
        }

        if (!$hasSeo) return;

        $propertyCode = $property->provider_code ?: $property->provider_property_id;
        
        $seoData = [
            'route_slug'       => $request->seo_slug ?? ('property-' . \Illuminate\Support\Str::slug($propertyCode)),
            'route_path'       => '/properties/' . $propertyCode,
            'route_label'      => 'Property: ' . $property->name,
            'route_group'      => 'Dynamic',
            'meta_title'       => $request->meta_title,
            'meta_description' => $request->meta_description,
            'og_title'         => $request->og_title,
            'og_description'   => $request->og_description,
            'twitter_title'    => $request->twitter_title,
            'twitter_description' => $request->twitter_description,
            'canonical_url'    => $request->canonical_url,
            'no_index'         => $request->has('no_index') ? filter_var($request->no_index, FILTER_VALIDATE_BOOLEAN) : false,
            'no_follow'        => $request->has('no_follow') ? filter_var($request->no_follow, FILTER_VALIDATE_BOOLEAN) : false,
            'schema_markup'    => $request->schema_markup,
            'is_active'        => true,
        ];

        // Handle Image Uploads for SEO
        if ($request->hasFile('og_image')) {
            $seoData['og_image'] = $this->handleFileUpload($request->file('og_image'), 'properties/seo');
        } elseif ($request->filled('og_image')) {
            $seoData['og_image'] = $request->og_image;
        }

        if ($request->hasFile('twitter_image')) {
            $seoData['twitter_image'] = $this->handleFileUpload($request->file('twitter_image'), 'properties/seo');
        } elseif ($request->filled('twitter_image')) {
            $seoData['twitter_image'] = $request->twitter_image;
        }

        \App\Models\SeoConfig::updateOrCreate(
            ['model_type' => PropertyListing::class, 'model_id' => $property->id],
            array_filter($seoData, fn($value) => !is_null($value))
        );
    }

    /**
     * Handle file upload helper
     */
    protected function handleFileUpload($file, $folder)
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');
        return '/storage/' . $path;
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
                'bedrooms'       => $bedrooms,
                'bathrooms'      => $bathrooms,
                'max_guests'     => (int)($data['max_occupancy'] ?? $data['guests'] ?? 0),
                'destination_code' => $data['destination_code'] ?? null,
                'price_from'     => $data['rate'] ?? $data['price'] ?? $unit['unitMonetaryInformation']['baseRate'] ?? null,
                'price_currency' => $currency,
                'api_data'       => $data,
                'last_synced_at' => now(),
                'is_active'      => (bool)($data['active'] ?? true),
                'instant_book'   => (bool)($data['instant_book'] ?? false),
            ]
        );
    }
}
