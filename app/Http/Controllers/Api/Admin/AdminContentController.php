<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Content (CMS)",
 *     description="Full page builder with SEO, nav management, and dynamic header/footer"
 * )
 */
class AdminContentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/content",
     *     summary="List all content pages",
     *     tags={"Admin - Content (CMS)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"page","header","footer","section","nav"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="show_in_nav", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Paginated content list")
     * )
     */
    public function index(Request $request)
    {
        $pages = ContentPage::withTrashed()
            ->when($request->type,        fn($q) => $q->where('type', $request->type))
            ->when($request->show_in_nav, fn($q) => $q->where('show_in_nav', true))
            ->when($request->search,      fn($q) => $q->where(fn($q2) => $q2
                ->where('title', 'like', "%{$request->search}%")
                ->orWhere('slug',  'like', "%{$request->search}%")
            ))
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $pages]);
    }

    /**
     * @OA\Post(
     *     path="/admin/content",
     *     summary="Create a content page",
     *     tags={"Admin - Content (CMS)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"title","type"},
     *         @OA\Property(property="title",            type="string",  example="About Us"),
     *         @OA\Property(property="type",             type="string",  enum={"page","header","footer","section","nav"}, example="page"),
     *         @OA\Property(property="template",         type="string",  enum={"default","landing","minimal","full-width"}, example="default"),
     *         @OA\Property(property="slug",             type="string",  example="about-us"),
     *         @OA\Property(property="parent_slug",      type="string",  example=""),
     *         @OA\Property(property="content",          type="object",  description="Flexible JSON content block"),
     *         @OA\Property(property="sections",         type="array",   @OA\Items(type="object"), description="Page builder sections"),
     *         @OA\Property(property="featured_image",   type="string"),
     *         @OA\Property(property="meta_title",       type="string"),
     *         @OA\Property(property="meta_description", type="string"),
     *         @OA\Property(property="og_title",         type="string"),
     *         @OA\Property(property="og_description",   type="string"),
     *         @OA\Property(property="og_image",         type="string"),
     *         @OA\Property(property="canonical_url",    type="string"),
     *         @OA\Property(property="no_index",         type="boolean", example=false),
     *         @OA\Property(property="schema_markup",    type="object",  description="JSON-LD structured data"),
     *         @OA\Property(property="show_in_nav",      type="boolean", example=false),
     *         @OA\Property(property="nav_label",        type="string"),
     *         @OA\Property(property="nav_icon",         type="string"),
     *         @OA\Property(property="sort_order",       type="integer", example=0),
     *         @OA\Property(property="is_active",        type="boolean", example=true),
     *         @OA\Property(property="published_at",     type="string",  format="date-time")
     *     )),
     *     @OA\Response(response=201, description="Page created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'type'             => 'required|in:page,header,footer,section,nav',
            'template'         => 'nullable|in:default,landing,minimal,full-width',
            'slug'             => 'nullable|string|unique:content_pages,slug',
            'parent_slug'      => 'nullable|string',
            'content'          => 'nullable',
            'sections'         => 'nullable|array',
            'featured_image'   => 'nullable|string',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title'         => 'nullable|string|max:255',
            'og_description'   => 'nullable|string|max:500',
            'og_image'         => 'nullable|string',
            'canonical_url'    => 'nullable|url',
            'no_index'         => 'boolean',
            'schema_markup'    => 'nullable|array',
            'show_in_nav'      => 'boolean',
            'nav_label'        => 'nullable|string|max:100',
            'nav_icon'         => 'nullable|string|max:100',
            'sort_order'       => 'nullable|integer',
            'is_active'        => 'boolean',
            'published_at'     => 'nullable|date',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']);
        $validated['template'] = $validated['template'] ?? 'default';

        $page = ContentPage::create($validated);

        $this->flushContentCache($page->type, $page->slug);

        return response()->json(['success' => true, 'data' => $page->append('seo')], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/content/{id}",
     *     summary="Get a single content page with full SEO data",
     *     tags={"Admin - Content (CMS)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Page details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $page = ContentPage::withTrashed()->findOrFail($id);
        return response()->json(['success' => true, 'data' => $page->append('seo')]);
    }

    /**
     * @OA\Put(
     *     path="/admin/content/{id}",
     *     summary="Update a content page",
     *     tags={"Admin - Content (CMS)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="title",            type="string",  example="About Us"),
     *         @OA\Property(property="type",             type="string",  enum={"page","header","footer","section","nav"}),
     *         @OA\Property(property="template",         type="string",  enum={"default","landing","minimal","full-width"}),
     *         @OA\Property(property="slug",             type="string",  example="about-us"),
     *         @OA\Property(property="parent_slug",      type="string",  example=""),
     *         @OA\Property(property="content",          type="object",  description="Flexible JSON content block"),
     *         @OA\Property(property="sections",         type="array",   @OA\Items(type="object"), description="Page builder sections"),
     *         @OA\Property(property="featured_image",   type="string"),
     *         @OA\Property(property="meta_title",       type="string"),
     *         @OA\Property(property="meta_description", type="string"),
     *         @OA\Property(property="og_title",         type="string"),
     *         @OA\Property(property="og_description",   type="string"),
     *         @OA\Property(property="og_image",         type="string"),
     *         @OA\Property(property="canonical_url",    type="string"),
     *         @OA\Property(property="no_index",         type="boolean", example=false),
     *         @OA\Property(property="schema_markup",    type="object",  description="JSON-LD structured data"),
     *         @OA\Property(property="show_in_nav",      type="boolean", example=false),
     *         @OA\Property(property="nav_label",        type="string"),
     *         @OA\Property(property="nav_icon",         type="string"),
     *         @OA\Property(property="sort_order",       type="integer", example=0),
     *         @OA\Property(property="is_active",        type="boolean", example=true),
     *         @OA\Property(property="published_at",     type="string",  format="date-time")
     *     )),
     *     @OA\Response(response=200, description="Page updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $page = ContentPage::findOrFail($id);

        $validated = $request->validate([
            'title'            => 'nullable|string|max:255',
            'type'             => 'nullable|in:page,header,footer,section,nav',
            'template'         => 'nullable|in:default,landing,minimal,full-width',
            'slug'             => 'nullable|string|unique:content_pages,slug,' . $id,
            'parent_slug'      => 'nullable|string',
            'content'          => 'nullable',
            'sections'         => 'nullable|array',
            'featured_image'   => 'nullable|string',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title'         => 'nullable|string|max:255',
            'og_description'   => 'nullable|string|max:500',
            'og_image'         => 'nullable|string',
            'canonical_url'    => 'nullable|url',
            'no_index'         => 'boolean',
            'schema_markup'    => 'nullable|array',
            'show_in_nav'      => 'boolean',
            'nav_label'        => 'nullable|string|max:100',
            'nav_icon'         => 'nullable|string|max:100',
            'sort_order'       => 'nullable|integer',
            'is_active'        => 'boolean',
            'published_at'     => 'nullable|date',
        ]);

        $page->update($validated);

        $this->flushContentCache($page->type, $page->slug);

        return response()->json(['success' => true, 'data' => $page->fresh()->append('seo')]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/content/{id}",
     *     summary="Soft-delete a content page",
     *     tags={"Admin - Content (CMS)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Page deleted")
     * )
     */
    public function destroy($id)
    {
        $page = ContentPage::findOrFail($id);
        $this->flushContentCache($page->type, $page->slug);
        $page->delete();

        return response()->json(['success' => true, 'message' => 'Content page deleted.']);
    }

    /**
     * @OA\Put(
     *     path="/admin/content/{id}/restore",
     *     summary="Restore a soft-deleted content page",
     *     tags={"Admin - Content (CMS)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Page restored")
     * )
     */
    public function restore($id)
    {
        $page = ContentPage::withTrashed()->findOrFail($id);
        $page->restore();

        return response()->json(['success' => true, 'data' => $page]);
    }

    /**
     * @OA\Post(
     *     path="/admin/content/reorder",
     *     summary="Reorder nav items (drag-and-drop sort)",
     *     tags={"Admin - Content (CMS)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"order"},
     *         @OA\Property(property="order", type="array", @OA\Items(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="sort_order", type="integer")
     *         ))
     *     )),
     *     @OA\Response(response=200, description="Order updated")
     * )
     */
    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*.id' => 'required|integer', 'order.*.sort_order' => 'required|integer']);

        foreach ($request->order as $item) {
            ContentPage::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        Cache::forget('nav_menu');

        return response()->json(['success' => true, 'message' => 'Nav order updated.']);
    }

    // ── Private helpers ────────────────────────────────────────────────────────
    private function flushContentCache(string $type, string $slug): void
    {
        Cache::forget("content_page_{$slug}");
        Cache::forget('content_header');
        Cache::forget('content_footer');
        Cache::forget('nav_menu');
        if ($type === 'header') Cache::forget('site_header');
        if ($type === 'footer') Cache::forget('site_footer');
    }
}
