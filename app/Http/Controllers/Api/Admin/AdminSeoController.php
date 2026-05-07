<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoConfig;
use App\Models\PropertyListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - SEO",
 *     description="Manage SEO meta for every page — home, properties, booking, search, etc. Each route gets its own title, description, OG tags, Twitter card, canonical URL, robots directives, and JSON-LD schema."
 * )
 */
class AdminSeoController extends Controller
{
    // Predefined application routes (all pages that need SEO)
    private const PREDEFINED_ROUTES = [
        // ── Core ────────────────────────────────────────────────────
        ['group' => 'Core',    'route_slug' => 'home',              'route_label' => 'Homepage',                    'route_path' => '/'],
        ['group' => 'Core',    'route_slug' => 'about-us',          'route_label' => 'About Us',                    'route_path' => '/about-us'],
        ['group' => 'Core',    'route_slug' => 'contact',           'route_label' => 'Contact Us',                  'route_path' => '/contact'],
        ['group' => 'Core',    'route_slug' => 'faq',               'route_label' => 'FAQ',                         'route_path' => '/faq'],
        ['group' => 'Core',    'route_slug' => 'terms',             'route_label' => 'Terms & Conditions',          'route_path' => '/terms'],
        ['group' => 'Core',    'route_slug' => 'privacy-policy',    'route_label' => 'Privacy Policy',              'route_path' => '/privacy-policy'],
        ['group' => 'Core',    'route_slug' => 'cookie-policy',     'route_label' => 'Cookie Policy',               'route_path' => '/cookie-policy'],

        // ── Search & Listings ────────────────────────────────────────
        ['group' => 'Search',  'route_slug' => 'search',            'route_label' => 'Search Results',              'route_path' => '/search'],
        ['group' => 'Search',  'route_slug' => 'properties',        'route_label' => 'All Properties',              'route_path' => '/properties'],
        ['group' => 'Search',  'route_slug' => 'hotels',            'route_label' => 'Hotels',                      'route_path' => '/hotels'],
        ['group' => 'Search',  'route_slug' => 'vacation-rentals',  'route_label' => 'Vacation Rentals',            'route_path' => '/vacation-rentals'],

        // ── Booking flow ─────────────────────────────────────────────
        ['group' => 'Booking', 'route_slug' => 'booking',           'route_label' => 'Booking Page',                'route_path' => '/booking'],
        ['group' => 'Booking', 'route_slug' => 'booking-review',    'route_label' => 'Booking Review / Checkout',   'route_path' => '/booking/review'],
        ['group' => 'Booking', 'route_slug' => 'booking-payment',   'route_label' => 'Booking Payment',             'route_path' => '/booking/payment'],
        ['group' => 'Booking', 'route_slug' => 'booking-confirm',   'route_label' => 'Booking Confirmation',        'route_path' => '/booking/confirm'],
        ['group' => 'Booking', 'route_slug' => 'booking-failed',    'route_label' => 'Booking / Payment Failed',    'route_path' => '/booking/failed'],
        ['group' => 'Booking', 'route_slug' => 'my-bookings',       'route_label' => 'My Bookings',                 'route_path' => '/my-bookings'],

        // ── Account ──────────────────────────────────────────────────
        ['group' => 'Account', 'route_slug' => 'login',             'route_label' => 'Login',                       'route_path' => '/login'],
        ['group' => 'Account', 'route_slug' => 'register',          'route_label' => 'Register',                    'route_path' => '/register'],
        ['group' => 'Account', 'route_slug' => 'forgot-password',   'route_label' => 'Forgot Password',             'route_path' => '/forgot-password'],
        ['group' => 'Account', 'route_slug' => 'profile',           'route_label' => 'My Profile',                  'route_path' => '/profile'],
        ['group' => 'Account', 'route_slug' => 'wishlist',          'route_label' => 'Wishlist',                    'route_path' => '/wishlist'],

        // ── Blog ─────────────────────────────────────────────────────
        ['group' => 'Blog',    'route_slug' => 'blog',              'route_label' => 'Blog Index',                  'route_path' => '/blog'],

        // ── Dynamic templates (one config = default for all pages of that type)
        ['group' => 'Dynamic', 'route_slug' => 'property-detail',   'route_label' => 'Property Detail — default template',  'route_path' => '/properties/{id}'],
        ['group' => 'Dynamic', 'route_slug' => 'hotel-detail',      'route_label' => 'Hotel Detail — default template',     'route_path' => '/hotels/{id}'],
        ['group' => 'Dynamic', 'route_slug' => 'destination',       'route_label' => 'Destination — default template',      'route_path' => '/destinations/{slug}'],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/admin/seo",
     *     summary="List all SEO configurations",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="group",    in="query", description="Filter by group: Core|Search|Booking|Account|Blog|Dynamic", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search",   in="query", description="Search by slug, label or meta_title", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=50)),
     *     @OA\Response(response=200, description="Paginated SEO config list")
     * )
     */
    public function index(Request $request)
    {
        $query = SeoConfig::query()
            ->when($request->group,  fn ($q) => $q->where('route_group', $request->group))
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('route_slug',  'like', "%{$request->search}%")
                ->orWhere('route_label','like', "%{$request->search}%")
                ->orWhere('meta_title', 'like', "%{$request->search}%")
            ))
            ->orderByRaw("FIELD(route_group,'Core','Search','Booking','Account','Blog','Dynamic','General')")
            ->orderBy('route_slug');

        return response()->json(['success' => true, 'data' => $query->paginate((int) $request->get('per_page', 50))]);
    }

    /**
     * @OA\Get(
     *     path="/admin/seo/routes",
     *     summary="All predefined app routes with current SEO status",
     *     description="Returns every page of the application grouped by section. Each entry shows whether SEO has been configured or is still missing (seo_config: null).",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Route list with SEO status")
     * )
     */
    public function routes()
    {
        $slugs    = collect(self::PREDEFINED_ROUTES)->pluck('route_slug');
        $existing = SeoConfig::whereIn('route_slug', $slugs)
            ->get(['id','route_slug','meta_title','meta_description','og_image','no_index','is_active'])
            ->keyBy('route_slug');

        $grouped = collect(self::PREDEFINED_ROUTES)
            ->map(fn ($r) => array_merge($r, ['seo_config' => $existing->get($r['route_slug'])]))
            ->groupBy('group');

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    /**
     * @OA\Post(
     *     path="/admin/seo",
     *     summary="Create / set SEO for a route",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"route_slug","meta_title"},
     *         @OA\Property(property="route_slug",        type="string",  example="home",           description="Route key — must match predefined slugs or any custom key"),
     *         @OA\Property(property="route_path",        type="string",  example="/",              description="Frontend URL path"),
     *         @OA\Property(property="route_label",       type="string",  example="Homepage"),
     *         @OA\Property(property="route_group",       type="string",  example="Core",           enum={"Core","Search","Booking","Account","Blog","Dynamic","General"}),
     *         @OA\Property(property="meta_title",        type="string",  example="PikPakGo — Vacation Rentals & Hotels"),
     *         @OA\Property(property="meta_description",  type="string",  example="Find the best vacation rentals and hotels worldwide."),
     *         @OA\Property(property="og_title",          type="string"),
     *         @OA\Property(property="og_description",    type="string"),
     *         @OA\Property(property="og_image",          type="string",  example="https://cdn.pikpakgo.com/og-default.jpg"),
     *         @OA\Property(property="twitter_card",      type="string",  enum={"summary","summary_large_image"}, example="summary_large_image"),
     *         @OA\Property(property="twitter_title",     type="string"),
     *         @OA\Property(property="twitter_description",type="string"),
     *         @OA\Property(property="twitter_image",     type="string"),
     *         @OA\Property(property="canonical_url",     type="string",  example="https://pikpakgo.com/"),
     *         @OA\Property(property="no_index",          type="boolean", example=false),
     *         @OA\Property(property="no_follow",         type="boolean", example=false),
     *         @OA\Property(property="schema_markup",     type="object",  description="Custom JSON-LD — leave null to auto-generate"),
     *         @OA\Property(property="is_active",         type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="SEO config created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_slug'        => 'required|string|max:200|unique:seo_configs,route_slug',
            'route_path'        => 'nullable|string|max:300',
            'route_label'       => 'nullable|string|max:200',
            'route_group'       => 'nullable|string|max:50',
            'model_type'        => 'nullable|string|max:100',
            'model_id'          => 'nullable|integer',
            'meta_title'        => 'required|string|max:255',
            'meta_description'  => 'nullable|string|max:500',
            'og_title'          => 'nullable|string|max:255',
            'og_description'    => 'nullable|string|max:500',
            'og_image'          => 'nullable|string|max:500',
            'twitter_card'      => 'nullable|in:summary,summary_large_image',
            'twitter_title'     => 'nullable|string|max:255',
            'twitter_description'=> 'nullable|string|max:500',
            'twitter_image'     => 'nullable|string|max:500',
            'canonical_url'     => 'nullable|url|max:500',
            'no_index'          => 'boolean',
            'no_follow'         => 'boolean',
            'schema_markup'     => 'nullable|array',
            'is_active'         => 'boolean',
        ]);

        $seo = SeoConfig::create($validated);
        Cache::forget("seo_{$seo->route_slug}");

        return response()->json(['success' => true, 'data' => array_merge($seo->toArray(), ['seo' => $seo->seo])], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/seo/{id}",
     *     summary="Get a single SEO config",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="SEO config detail"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $seo = SeoConfig::findOrFail($id);
        return response()->json(['success' => true, 'data' => array_merge($seo->toArray(), ['seo' => $seo->seo])]);
    }

    /**
     * @OA\Put(
     *     path="/admin/seo/{id}",
     *     summary="Update SEO config for a route",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="meta_title",        type="string",  example="Updated Page Title | PikPakGo"),
     *         @OA\Property(property="meta_description",  type="string",  example="Updated meta description."),
     *         @OA\Property(property="og_title",          type="string"),
     *         @OA\Property(property="og_description",    type="string"),
     *         @OA\Property(property="og_image",          type="string"),
     *         @OA\Property(property="twitter_card",      type="string",  enum={"summary","summary_large_image"}),
     *         @OA\Property(property="twitter_title",     type="string"),
     *         @OA\Property(property="twitter_description",type="string"),
     *         @OA\Property(property="twitter_image",     type="string"),
     *         @OA\Property(property="canonical_url",     type="string"),
     *         @OA\Property(property="no_index",          type="boolean"),
     *         @OA\Property(property="no_follow",         type="boolean"),
     *         @OA\Property(property="schema_markup",     type="object"),
     *         @OA\Property(property="is_active",         type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $seo = SeoConfig::findOrFail($id);

        $validated = $request->validate([
            'meta_title'        => 'sometimes|string|max:255',
            'meta_description'  => 'nullable|string|max:500',
            'model_type'        => 'nullable|string|max:100',
            'model_id'          => 'nullable|integer',
            'og_title'          => 'nullable|string|max:255',
            'og_description'    => 'nullable|string|max:500',
            'og_image'          => 'nullable|string|max:500',
            'twitter_card'      => 'nullable|in:summary,summary_large_image',
            'twitter_title'     => 'nullable|string|max:255',
            'twitter_description'=> 'nullable|string|max:500',
            'twitter_image'     => 'nullable|string|max:500',
            'canonical_url'     => 'nullable|url|max:500',
            'no_index'          => 'boolean',
            'no_follow'         => 'boolean',
            'schema_markup'     => 'nullable|array',
            'is_active'         => 'boolean',
        ]);

        $seo->update($validated);
        Cache::forget("seo_{$seo->route_slug}");

        return response()->json(['success' => true, 'data' => array_merge($seo->fresh()->toArray(), ['seo' => $seo->fresh()->seo])]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/seo/{id}",
     *     summary="Delete SEO config (page reverts to site defaults)",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $seo = SeoConfig::findOrFail($id);
        Cache::forget("seo_{$seo->route_slug}");
        $seo->delete();

        return response()->json(['success' => true, 'message' => 'SEO config deleted. Page will use site defaults.']);
    }

    /**
     * @OA\Post(
     *     path="/admin/seo/bulk-update",
     *     summary="Bulk upsert SEO for multiple routes at once",
     *     description="Ideal for initial setup — pass all pages in one request. Existing configs are updated; missing ones are created.",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"pages"},
     *         @OA\Property(property="pages", type="array", @OA\Items(
     *             required={"route_slug","meta_title"},
     *             @OA\Property(property="route_slug",       type="string", example="home"),
     *             @OA\Property(property="route_path",       type="string", example="/"),
     *             @OA\Property(property="route_label",      type="string", example="Homepage"),
     *             @OA\Property(property="route_group",      type="string", example="Core"),
     *             @OA\Property(property="meta_title",       type="string", example="PikPakGo — Vacation Rentals"),
     *             @OA\Property(property="meta_description", type="string"),
     *             @OA\Property(property="og_image",         type="string"),
     *             @OA\Property(property="no_index",         type="boolean", example=false)
     *         ))
     *     )),
     *     @OA\Response(response=200, description="Bulk update result")
     * )
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'pages'                       => 'required|array|min:1',
            'pages.*.route_slug'          => 'required|string|max:200',
            'pages.*.meta_title'          => 'required|string|max:255',
            'pages.*.route_path'          => 'nullable|string|max:300',
            'pages.*.route_label'         => 'nullable|string|max:200',
            'pages.*.route_group'         => 'nullable|string|max:50',
            'pages.*.meta_description'    => 'nullable|string|max:500',
            'pages.*.og_title'            => 'nullable|string|max:255',
            'pages.*.og_description'      => 'nullable|string|max:500',
            'pages.*.og_image'            => 'nullable|string|max:500',
            'pages.*.canonical_url'       => 'nullable|url|max:500',
            'pages.*.no_index'            => 'nullable|boolean',
            'pages.*.no_follow'           => 'nullable|boolean',
            'pages.*.schema_markup'       => 'nullable|array',
        ]);

        $updated = 0;
        $created = 0;

        foreach ($request->pages as $item) {
            SeoConfig::updateOrCreate(
                ['route_slug' => $item['route_slug']],
                array_merge($item, ['is_active' => true])
            );
            Cache::forget("seo_{$item['route_slug']}");
            $existing = SeoConfig::where('route_slug', $item['route_slug'])->exists();
            $existing ? $updated++ : $created++;
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk SEO update complete.",
            'stats'   => ['updated' => $updated, 'created' => $created],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/seo/property/{propertyCode}",
     *     summary="Preview auto-generated SEO for a specific property page",
     *     tags={"Admin - SEO"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyCode", in="path", required=true, @OA\Schema(type="string", example="OR-12345")),
     *     @OA\Response(response=200, description="Property SEO preview"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function propertySeoPeview($propertyCode)
    {
        $property = PropertyListing::where('provider_code', $propertyCode)
            ->orWhere('provider_property_id', $propertyCode)
            ->firstOrFail();

        $seoData = app(\App\Services\SeoService::class)->getPropertySeo($property);

        return response()->json([
            'success' => true,
            'source'  => $seoData['source'],
            'slug'    => $seoData['slug'],
            'data'    => $seoData['data'],
        ]);
    }
}
