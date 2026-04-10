<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\PropertyListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - SEO",
 *     description="Manage per-route SEO meta — title, description, OG tags, schema markup, canonical URL, robots"
 * )
 */
class AdminSeoController extends Controller
{
    // All content_pages with type='seo' are dedicated route SEO entries.
    // Pages with other types (page, header, footer, etc.) also carry SEO fields
    // but are managed via AdminContentController. This controller focuses on
    // SEO-only entries (routes without a full content page).

    /**
     * @OA\Get(
     *     path="/admin/seo",
     *     summary="List all SEO configurations",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", description="Filter: seo|page|all (default: all)", @OA\Schema(type="string", enum={"seo","page","all"}, example="all")),
     *     @OA\Parameter(name="search", in="query", description="Search by slug or title", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Paginated SEO list")
     * )
     */
    public function index(Request $request)
    {
        $type    = $request->get('type', 'all');
        $search  = $request->get('search');
        $perPage = min((int) $request->get('per_page', 20), 100);

        $query = ContentPage::withTrashed()
            ->select([
                'id', 'slug', 'title', 'type',
                'meta_title', 'meta_description',
                'og_title', 'og_description', 'og_image',
                'canonical_url', 'no_index', 'schema_markup',
                'is_active', 'deleted_at',
            ])
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('slug', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('meta_title', 'like', "%{$search}%")
            ))
            ->orderBy('type')
            ->orderBy('slug');

        return response()->json(['success' => true, 'data' => $query->paginate($perPage)]);
    }

    /**
     * @OA\Get(
     *     path="/admin/seo/routes",
     *     summary="List predefined application routes for SEO management",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Predefined routes with their current SEO status")
     * )
     */
    public function routes()
    {
        $predefinedRoutes = [
            // ── Core pages ─────────────────────────────────────────────────
            ['group' => 'Core',    'slug' => 'home',                'label' => 'Homepage',                        'path' => '/'],
            ['group' => 'Core',    'slug' => 'about-us',            'label' => 'About Us',                        'path' => '/about-us'],
            ['group' => 'Core',    'slug' => 'contact',             'label' => 'Contact Us',                      'path' => '/contact'],
            ['group' => 'Core',    'slug' => 'faq',                 'label' => 'FAQ',                             'path' => '/faq'],
            ['group' => 'Core',    'slug' => 'terms',               'label' => 'Terms & Conditions',              'path' => '/terms'],
            ['group' => 'Core',    'slug' => 'privacy-policy',      'label' => 'Privacy Policy',                  'path' => '/privacy-policy'],
            ['group' => 'Core',    'slug' => 'cookie-policy',       'label' => 'Cookie Policy',                   'path' => '/cookie-policy'],

            // ── Search & Properties ─────────────────────────────────────────
            ['group' => 'Search',  'slug' => 'search',              'label' => 'Search Results',                  'path' => '/search'],
            ['group' => 'Search',  'slug' => 'properties',          'label' => 'All Properties Listing',          'path' => '/properties'],
            ['group' => 'Search',  'slug' => 'hotels',              'label' => 'Hotels Listing',                  'path' => '/hotels'],
            ['group' => 'Search',  'slug' => 'vacation-rentals',    'label' => 'Vacation Rentals Listing',        'path' => '/vacation-rentals'],

            // ── Booking flow ───────────────────────────────────────────────
            ['group' => 'Booking', 'slug' => 'booking',             'label' => 'Booking Page',                    'path' => '/booking'],
            ['group' => 'Booking', 'slug' => 'booking-review',      'label' => 'Booking Review / Checkout',       'path' => '/booking/review'],
            ['group' => 'Booking', 'slug' => 'booking-payment',     'label' => 'Booking Payment Step',            'path' => '/booking/payment'],
            ['group' => 'Booking', 'slug' => 'booking-confirm',     'label' => 'Booking Confirmation',            'path' => '/booking/confirm'],
            ['group' => 'Booking', 'slug' => 'booking-failed',      'label' => 'Booking / Payment Failed',        'path' => '/booking/failed'],
            ['group' => 'Booking', 'slug' => 'my-bookings',         'label' => 'My Bookings (dashboard)',         'path' => '/my-bookings'],

            // ── Account ────────────────────────────────────────────────────
            ['group' => 'Account', 'slug' => 'login',               'label' => 'Login Page',                      'path' => '/login'],
            ['group' => 'Account', 'slug' => 'register',            'label' => 'Register Page',                   'path' => '/register'],
            ['group' => 'Account', 'slug' => 'forgot-password',     'label' => 'Forgot Password',                 'path' => '/forgot-password'],
            ['group' => 'Account', 'slug' => 'profile',             'label' => 'My Profile',                      'path' => '/profile'],
            ['group' => 'Account', 'slug' => 'wishlist',            'label' => 'Wishlist',                        'path' => '/wishlist'],

            // ── Dynamic page templates (admin sets default SEO for type) ──
            ['group' => 'Dynamic', 'slug' => 'property-detail',     'label' => 'Property Detail — default template',   'path' => '/properties/{id}',          'note' => 'Auto-generated per property from DB. Override via POST /admin/seo with slug=property-{code}'],
            ['group' => 'Dynamic', 'slug' => 'destination',         'label' => 'Destination Page — default template',  'path' => '/destinations/{slug}',      'note' => 'Create slug=destination-{city-name} to override per destination'],
            ['group' => 'Dynamic', 'slug' => 'hotel-detail',        'label' => 'Hotel Detail — default template',      'path' => '/hotels/{id}',              'note' => 'Auto-generated per property from DB'],
        ];

        // Attach existing SEO records
        $slugs    = collect($predefinedRoutes)->pluck('slug');
        $existing = ContentPage::whereIn('slug', $slugs)
            ->select(['id', 'slug', 'meta_title', 'meta_description', 'og_title', 'no_index', 'is_active'])
            ->get()
            ->keyBy('slug');

        $result = collect($predefinedRoutes)->map(fn ($route) => array_merge(
            $route,
            ['seo' => $existing->get($route['slug'])]
        ));

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * @OA\Post(
     *     path="/admin/seo",
     *     summary="Create an SEO configuration for a route",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"slug","title"},
     *         @OA\Property(property="slug",             type="string",  example="home",         description="Route identifier — use the page slug or a custom key"),
     *         @OA\Property(property="title",            type="string",  example="PikPakGo — Vacation Rentals & Hotels"),
     *         @OA\Property(property="meta_title",       type="string",  example="PikPakGo — Vacation Rentals & Hotels"),
     *         @OA\Property(property="meta_description", type="string",  example="Find the best vacation rentals and hotels worldwide."),
     *         @OA\Property(property="og_title",         type="string",  example="PikPakGo"),
     *         @OA\Property(property="og_description",   type="string"),
     *         @OA\Property(property="og_image",         type="string",  example="https://cdn.pikpakgo.com/og-default.jpg"),
     *         @OA\Property(property="canonical_url",    type="string",  example="https://pikpakgo.com/"),
     *         @OA\Property(property="no_index",         type="boolean", example=false),
     *         @OA\Property(property="schema_markup",    type="object",  description="JSON-LD structured data object"),
     *         @OA\Property(property="is_active",        type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="SEO config created"),
     *     @OA\Response(response=422, description="Validation error or slug already exists")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug'             => 'required|string|unique:content_pages,slug|max:200',
            'title'            => 'required|string|max:255',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title'         => 'nullable|string|max:255',
            'og_description'   => 'nullable|string|max:500',
            'og_image'         => 'nullable|string|max:500',
            'canonical_url'    => 'nullable|url|max:500',
            'no_index'         => 'boolean',
            'schema_markup'    => 'nullable|array',
            'is_active'        => 'boolean',
        ]);

        $page = ContentPage::create(array_merge($validated, ['type' => 'seo']));

        $this->flushSeoCache($page->slug);

        return response()->json(['success' => true, 'data' => array_merge($page->toArray(), ['seo' => $page->seo])], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/seo/{id}",
     *     summary="Get a single SEO configuration",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="SEO config details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $page = ContentPage::withTrashed()->findOrFail($id);
        return response()->json(['success' => true, 'data' => array_merge($page->toArray(), ['seo' => $page->seo])]);
    }

    /**
     * @OA\Put(
     *     path="/admin/seo/{id}",
     *     summary="Update an SEO configuration",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="title",            type="string"),
     *         @OA\Property(property="meta_title",       type="string",  example="Updated Page Title | PikPakGo"),
     *         @OA\Property(property="meta_description", type="string",  example="Updated meta description for SEO."),
     *         @OA\Property(property="og_title",         type="string"),
     *         @OA\Property(property="og_description",   type="string"),
     *         @OA\Property(property="og_image",         type="string"),
     *         @OA\Property(property="canonical_url",    type="string"),
     *         @OA\Property(property="no_index",         type="boolean"),
     *         @OA\Property(property="schema_markup",    type="object"),
     *         @OA\Property(property="is_active",        type="boolean")
     *     )),
     *     @OA\Response(response=200, description="SEO config updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $page = ContentPage::findOrFail($id);

        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title'         => 'nullable|string|max:255',
            'og_description'   => 'nullable|string|max:500',
            'og_image'         => 'nullable|string|max:500',
            'canonical_url'    => 'nullable|url|max:500',
            'no_index'         => 'boolean',
            'schema_markup'    => 'nullable|array',
            'is_active'        => 'boolean',
        ]);

        $page->update($validated);
        $this->flushSeoCache($page->slug);

        return response()->json(['success' => true, 'data' => array_merge($page->fresh()->toArray(), ['seo' => $page->fresh()->seo])]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/seo/{id}",
     *     summary="Delete an SEO configuration",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $page = ContentPage::findOrFail($id);
        $this->flushSeoCache($page->slug);
        $page->delete();

        return response()->json(['success' => true, 'message' => 'SEO config deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/admin/seo/bulk-update",
     *     summary="Bulk upsert SEO configs for multiple routes at once",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"pages"},
     *         @OA\Property(
     *             property="pages",
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="slug",             type="string", example="home"),
     *                 @OA\Property(property="title",            type="string", example="Homepage"),
     *                 @OA\Property(property="meta_title",       type="string"),
     *                 @OA\Property(property="meta_description", type="string"),
     *                 @OA\Property(property="og_title",         type="string"),
     *                 @OA\Property(property="og_description",   type="string"),
     *                 @OA\Property(property="og_image",         type="string"),
     *                 @OA\Property(property="canonical_url",    type="string"),
     *                 @OA\Property(property="no_index",         type="boolean"),
     *                 @OA\Property(property="schema_markup",    type="object")
     *             )
     *         )
     *     )),
     *     @OA\Response(response=200, description="Bulk update result")
     * )
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'pages'                     => 'required|array|min:1',
            'pages.*.slug'              => 'required|string|max:200',
            'pages.*.title'             => 'required|string|max:255',
            'pages.*.meta_title'        => 'nullable|string|max:255',
            'pages.*.meta_description'  => 'nullable|string|max:500',
            'pages.*.og_title'          => 'nullable|string|max:255',
            'pages.*.og_description'    => 'nullable|string|max:500',
            'pages.*.og_image'          => 'nullable|string|max:500',
            'pages.*.canonical_url'     => 'nullable|url|max:500',
            'pages.*.no_index'          => 'nullable|boolean',
            'pages.*.schema_markup'     => 'nullable|array',
        ]);

        $updated = 0;
        $created = 0;

        foreach ($request->pages as $item) {
            $slug = $item['slug'];

            $record = ContentPage::withTrashed()->where('slug', $slug)->first();

            if ($record) {
                $record->restore();
                $record->update(array_merge($item, ['type' => $record->type ?: 'seo']));
                $updated++;
            } else {
                ContentPage::create(array_merge($item, ['type' => 'seo', 'is_active' => true]));
                $created++;
            }

            $this->flushSeoCache($slug);
        }

        return response()->json([
            'success' => true,
            'message' => "SEO bulk update complete: {$updated} updated, {$created} created.",
            'stats'   => ['updated' => $updated, 'created' => $created],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/seo/property/{propertyCode}",
     *     summary="Get or preview auto-generated SEO for a specific property page",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyCode", in="path", required=true, @OA\Schema(type="string", example="OR-12345")),
     *     @OA\Response(response=200, description="Property SEO data"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function propertySeoPeview($propertyCode)
    {
        $property = PropertyListing::where('provider_code', $propertyCode)
            ->orWhere('provider_property_id', $propertyCode)
            ->firstOrFail();

        // Check for a custom override first
        $override = ContentPage::where('slug', 'property-' . Str::slug($propertyCode))
            ->where('type', 'seo')
            ->active()
            ->first();

        if ($override) {
            return response()->json([
                'success' => true,
                'source'  => 'override',
                'data'    => $override->seo,
                'override_id' => $override->id,
            ]);
        }

        // Auto-generate SEO from property data
        $seo = $this->generatePropertySeo($property);

        return response()->json([
            'success' => true,
            'source'  => 'auto-generated',
            'data'    => $seo,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function generatePropertySeo(PropertyListing $property): array
    {
        $title = $property->name . ' — ' . $property->city . ', ' . $property->country;
        $desc  = $property->description
            ? Str::limit(strip_tags($property->description), 155)
            : "Book {$property->name} in {$property->city}, {$property->country}. "
              . ucfirst($property->property_type) . ' available from $' . ($property->price_from ?? 'N/A') . '/night.';

        return [
            'title'          => $title,
            'description'    => $desc,
            'og_title'       => $title,
            'og_description' => $desc,
            'og_image'       => $property->featured_image,
            'canonical'      => null,
            'no_index'       => false,
            'schema'         => [
                '@context'    => 'https://schema.org',
                '@type'       => 'LodgingBusiness',
                'name'        => $property->name,
                'description' => $desc,
                'image'       => $property->images ?? [],
                'address'     => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => $property->address,
                    'addressLocality' => $property->city,
                    'addressRegion'   => $property->state,
                    'postalCode'      => $property->postal_code,
                    'addressCountry'  => $property->country_code ?? $property->country,
                ],
                'geo' => $property->latitude ? [
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => $property->latitude,
                    'longitude' => $property->longitude,
                ] : null,
                'starRating' => $property->star_rating ? [
                    '@type'       => 'Rating',
                    'ratingValue' => $property->star_rating,
                ] : null,
                'aggregateRating' => $property->rating_average ? [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => $property->rating_average,
                    'reviewCount' => $property->rating_count,
                ] : null,
                'priceRange' => $property->price_from ? ('$' . number_format($property->price_from, 0)) : null,
                'telephone'  => $property->phone,
                'email'      => $property->email,
                'url'        => $property->website,
            ],
        ];
    }

    private function flushSeoCache(string $slug): void
    {
        Cache::forget("seo_{$slug}");
        Cache::forget("content_page_{$slug}");
    }
}
