<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Support\Facades\Cache;

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
