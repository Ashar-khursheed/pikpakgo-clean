<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\ContentPage;
use App\Models\PropertyListing;
use App\Models\SeoConfig;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Public - Content",
 *     description="Public CMS content — pages, header, footer, nav, SEO meta"
 * )
 */
class ContentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/public/seo",
     *     summary="Resolve SEO meta for any frontend route",
     *     description="Call this before rendering any page. Pass `path` (the frontend URL) or `slug`. Returns title, meta description, OG tags, canonical URL, no-index flag, and JSON-LD schema markup. Falls back to site-wide defaults when no specific config exists.",
     *     tags={"Public - Content"},
     *     @OA\Parameter(
     *         name="path",
     *         in="query",
     *         description="Frontend URL path (e.g. /, /search, /about-us, /properties/OR-123)",
     *         @OA\Schema(type="string", example="/about-us")
     *     ),
     *     @OA\Parameter(
     *         name="slug",
     *         in="query",
     *         description="CMS slug or route key (e.g. home, about-us, search)",
     *         @OA\Schema(type="string", example="about-us")
     *     ),
     *     @OA\Parameter(
     *         name="property_code",
     *         in="query",
     *         description="Property code for auto-generated property page SEO",
     *         @OA\Schema(type="string", example="OR-12345")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resolved SEO meta block",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",  type="boolean"),
     *             @OA\Property(property="source",   type="string",  example="cms", description="cms|auto-generated|default"),
     *             @OA\Property(property="slug",     type="string",  example="about-us"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="title",          type="string"),
     *                 @OA\Property(property="description",    type="string"),
     *                 @OA\Property(property="og_title",       type="string"),
     *                 @OA\Property(property="og_description", type="string"),
     *                 @OA\Property(property="og_image",       type="string"),
     *                 @OA\Property(property="canonical",      type="string"),
     *                 @OA\Property(property="no_index",       type="boolean"),
     *                 @OA\Property(property="schema",         type="object", description="JSON-LD structured data")
     *             )
     *         )
     *     )
     * )
     */
    public function getSeo(Request $request)
    {
        $path         = $request->get('path');
        $slug         = $request->get('slug');
        $propertyCode = $request->get('property_code');

        // Resolve slug from path when not supplied directly
        if (!$slug && $path) {
            $slug = $this->pathToSlug($path);
        }

        $cacheKey = 'seo_' . ($propertyCode ?? $slug ?? 'home');

        $result = Cache::remember($cacheKey, 600, function () use ($slug, $path, $propertyCode) {
            // 1. Property page — check for manual override, then auto-generate
            if ($propertyCode) {
                $property = PropertyListing::where('provider_code', $propertyCode)
                    ->orWhere('provider_property_id', $propertyCode)
                    ->active()
                    ->first();

                if ($property) {
                    $seoData = app(\App\Services\SeoService::class)->getPropertySeo($property);
                    return array_merge(['source' => $seoData['source'], 'slug' => $seoData['slug']], ['data' => $seoData['data']]);
                }
            }

            // 2. Blog post SEO
            if ($slug && Str::startsWith($slug, 'blog-')) {
                $postSlug = Str::after($slug, 'blog-');
                $post = BlogPost::published()->where('slug', $postSlug)->first();
                if ($post) {
                    return ['source' => 'blog_post', 'slug' => $slug, 'data' => $post->seo];
                }
            }

            // 3. Dedicated seo_configs table (home, properties, booking, etc.)
            if ($slug) {
                $seoConfig = SeoConfig::where('route_slug', $slug)->where('is_active', true)->first();
                if ($seoConfig) {
                    return ['source' => 'seo_config', 'slug' => $slug, 'data' => $seoConfig->seo];
                }
            }

            // 4. CMS content pages (about-us, faq, terms, etc.)
            if ($slug) {
                $page = ContentPage::where('slug', $slug)->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
                    ->first();
                if ($page) {
                    return ['source' => 'cms', 'slug' => $slug, 'data' => $page->seo];
                }
            }

            // 5. Fallback — site-wide defaults from Settings
            return ['source' => 'default', 'slug' => $slug ?? $this->pathToSlug($path ?? '/'), 'data' => $this->siteDefaultSeo()];
        });

        return response()->json(array_merge(['success' => true], $result));
    }

    /**
     * @OA\Get(
     *     path="/public/seo/property/{propertyCode}",
     *     summary="Get auto-generated SEO for a specific property page",
     *     tags={"Public - Content"},
     *     @OA\Parameter(name="propertyCode", in="path", required=true, @OA\Schema(type="string", example="OR-12345")),
     *     @OA\Response(response=200, description="Property SEO meta"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function getPropertySeo($propertyCode)
    {
        $cacheKey = "seo_property_{$propertyCode}";

        $data = Cache::remember($cacheKey, 600, function () use ($propertyCode) {
            $property = PropertyListing::where('provider_code', $propertyCode)
                ->orWhere('provider_property_id', $propertyCode)
                ->active()
                ->first();

            if (!$property) return null;

            return app(\App\Services\SeoService::class)->getPropertySeo($property);
        });

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Property not found.'], 404);
        }

        return response()->json(array_merge(['success' => true, 'property_code' => $propertyCode], $data));
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /** Convert a frontend path to a CMS slug */
    private function pathToSlug(string $path): string
    {
        $path = ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        if ($path === '' || $path === '/') return 'home';
        return Str::slug(str_replace('/', '-', $path));
    }

    /** Site-wide SEO defaults pulled from Settings table */
    private function siteDefaultSeo(): array
    {
        $settings = Cache::remember('settings_public_seo', 3600, function () {
            return Setting::whereIn('key', [
                'site_name', 'site_description', 'site_logo', 'site_url',
                'default_og_image', 'default_meta_description',
            ])->pluck('value', 'key');
        });

        $siteName = $settings->get('site_name', 'PikPakGo');
        $desc     = $settings->get('default_meta_description', $settings->get('site_description',
            'Find vacation rentals and hotels worldwide. Book directly and save.'));

        return [
            'title'          => $siteName,
            'description'    => $desc,
            'og_title'       => $siteName,
            'og_description' => $desc,
            'og_image'       => $settings->get('default_og_image'),
            'canonical'      => $settings->get('site_url'),
            'no_index'       => false,
            'schema'         => [
                '@context' => 'https://schema.org',
                '@type'    => 'TravelAgency',
                'name'     => $siteName,
                'url'      => $settings->get('site_url'),
                'logo'     => $settings->get('site_logo'),
                'description' => $desc,
            ],
        ];
    }

    /**
     * @OA\Get(
     *     path="/public/content/pages/{slug}",
     *     summary="Get a CMS page by slug (with full SEO meta)",
     *     tags={"Public - Content"},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string", example="about-us")),
     *     @OA\Response(response=200, description="Page content + SEO"),
     *     @OA\Response(response=404, description="Page not found")
     * )
     */
    public function getPage($slug)
    {
        $page = Cache::remember("content_page_{$slug}", 600, fn() =>
            ContentPage::where('slug', $slug)
                ->whereIn('type', ['page', 'section'])
                ->active()
                ->published()
                ->first()
        );

        if (!$page) {
            return response()->json(['success' => false, 'message' => 'Page not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($page->toArray(), ['seo' => $page->seo]),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/public/content/header",
     *     summary="Get site header — logo, nav links, CTA buttons",
     *     tags={"Public - Content"},
     *     @OA\Response(response=200, description="Header configuration")
     * )
     */
    public function getHeader()
    {
        $data = Cache::remember('content_header', 600, function () {
            $header = ContentPage::where('type', 'header')->active()->first();

            // Primary nav (show_in_nav=true, type=page)
            $navLinks = ContentPage::active()
                ->published()
                ->inNav()
                ->whereNull('parent_slug')
                ->select('id', 'slug', 'nav_label', 'nav_icon', 'sort_order', 'parent_slug')
                ->get()
                ->map(function ($item) {
                    // Attach children
                    $item->children = ContentPage::active()
                        ->where('parent_slug', $item->slug)
                        ->inNav()
                        ->select('id', 'slug', 'nav_label', 'nav_icon', 'sort_order')
                        ->get();
                    return $item;
                });

            return [
                'logo'          => $header?->content['logo'] ?? null,
                'logo_dark'     => $header?->content['logo_dark'] ?? null,
                'site_name'     => $header?->content['site_name'] ?? config('app.name', 'PikPakGo'),
                'phone'         => $header?->content['phone'] ?? null,
                'email'         => $header?->content['email'] ?? null,
                'cta_text'      => $header?->content['cta_text'] ?? 'Book Now',
                'cta_link'      => $header?->content['cta_link'] ?? '/search',
                'announcement'  => $header?->content['announcement'] ?? null,
                'nav_links'     => $navLinks,
                'raw'           => $header?->content,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/public/content/footer",
     *     summary="Get site footer — links, socials, copyright, columns",
     *     tags={"Public - Content"},
     *     @OA\Response(response=200, description="Footer configuration")
     * )
     */
    public function getFooter()
    {
        $data = Cache::remember('content_footer', 600, function () {
            $footer = ContentPage::where('type', 'footer')->active()->first();

            // Footer nav columns (parent_slug = footer-column-1 / 2 / 3 etc.)
            $footerLinks = ContentPage::active()
                ->published()
                ->where('parent_slug', 'like', 'footer-%')
                ->inNav()
                ->select('id', 'slug', 'nav_label', 'nav_icon', 'parent_slug', 'sort_order')
                ->get()
                ->groupBy('parent_slug');

            return [
                'site_name'    => $footer?->content['site_name'] ?? config('app.name', 'PikPakGo'),
                'tagline'      => $footer?->content['tagline'] ?? null,
                'logo'         => $footer?->content['logo'] ?? null,
                'copyright'    => $footer?->content['copyright'] ?? '© ' . date('Y') . ' PikPakGo. All rights reserved.',
                'phone'        => $footer?->content['phone'] ?? null,
                'email'        => $footer?->content['email'] ?? null,
                'address'      => $footer?->content['address'] ?? null,
                'social_links' => $footer?->content['social_links'] ?? [
                    'facebook'  => null,
                    'instagram' => null,
                    'twitter'   => null,
                    'linkedin'  => null,
                    'youtube'   => null,
                ],
                'columns'      => $footerLinks,
                'badges'       => $footer?->content['badges'] ?? [],   // Payment icons, SSL badge etc.
                'raw'          => $footer?->content,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/public/content/nav",
     *     summary="Get full navigation menu (flat + hierarchical)",
     *     tags={"Public - Content"},
     *     @OA\Response(response=200, description="Navigation structure")
     * )
     */
    public function getNav()
    {
        $nav = Cache::remember('nav_menu', 600, fn() =>
            ContentPage::active()
                ->published()
                ->inNav()
                ->select('id', 'slug', 'nav_label', 'nav_icon', 'sort_order', 'parent_slug', 'template', 'type')
                ->get()
                ->groupBy(fn($item) => $item->parent_slug ?? '__root__')
        );

        $root = $nav->get('__root__', collect())->map(function ($item) use ($nav) {
            $item->children = $nav->get($item->slug, collect());
            return $item;
        });

        return response()->json(['success' => true, 'data' => $root]);
    }
}
