<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Blog",
 *     description="Full blog management — categories and posts with built-in SEO fields"
 * )
 */
class AdminBlogController extends Controller
{
    // =========================================================
    // CATEGORIES
    // =========================================================

    /**
     * @OA\Get(
     *     path="/admin/blog/categories",
     *     summary="List all blog categories",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search",   in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active",in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Category list with post counts")
     * )
     */
    public function categoryIndex(Request $request)
    {
        $categories = BlogCategory::withCount('posts')
            ->when($request->search,    fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * @OA\Post(
     *     path="/admin/blog/categories",
     *     summary="Create a blog category",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"name"},
     *             @OA\Property(property="name",             type="string",  example="Travel Tips"),
     *             @OA\Property(property="slug",             type="string",  example="travel-tips"),
     *             @OA\Property(property="description",      type="string"),
     *             @OA\Property(property="featured_image",   type="string",  format="binary"),
     *             @OA\Property(property="color",            type="string",  example="#3B82F6"),
     *             @OA\Property(property="sort_order",       type="integer", example=0),
     *             @OA\Property(property="is_active",        type="boolean", example=true),
     *             @OA\Property(property="meta_title",       type="string"),
     *             @OA\Property(property="meta_description", type="string"),
     *             @OA\Property(property="og_image",         type="string",  format="binary")
     *         )
     *     )),
     *     @OA\Response(response=201, description="Category created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function categoryStore(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'slug'             => 'nullable|string|unique:blog_categories,slug|max:120',
            'description'      => 'nullable|string|max:1000',
            'featured_image'   => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'color'            => 'nullable|string|max:20',
            'sort_order'       => 'nullable|integer',
            'is_active'        => 'nullable',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image'         => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = filter_var($request->is_active ?? true, FILTER_VALIDATE_BOOLEAN);

        // Handle Image Uploads
        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $this->handleFileUpload($request->file('featured_image'), 'blog/categories');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = $this->handleFileUpload($request->file('og_image'), 'blog/seo');
        }

        $category = BlogCategory::create($validated);

        // Sync to seo_configs table
        if ($request->filled('meta_title') || $request->filled('meta_description')) {
            \App\Models\SeoConfig::updateOrCreate(
                ['model_type' => BlogCategory::class, 'model_id' => $category->id],
                [
                    'route_slug'       => 'blog-category-' . $category->slug,
                    'route_path'       => '/blog/category/' . $category->slug,
                    'route_label'      => 'Blog Category: ' . $category->name,
                    'route_group'      => 'Blog',
                    'meta_title'       => $request->meta_title ?? ($category->name . ' — Blog'),
                    'meta_description' => $request->meta_description ?? $category->description,
                    'og_image'         => $category->og_image,
                    'is_active'        => true,
                ]
            );
        }

        Cache::forget('blog_categories');

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/blog/categories/{id}",
     *     summary="Get a single blog category",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Category details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function categoryShow($id)
    {
        $category = BlogCategory::withCount('posts')->findOrFail($id);
        return response()->json(['success' => true, 'data' => array_merge($category->toArray(), ['seo' => $category->seo])]);
    }

    /**
     * @OA\Put(
     *     path="/admin/blog/categories/{id}",
     *     summary="Update a blog category",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(property="_method",          type="string",  example="PUT", description="Method spoofing for file uploads"),
     *             @OA\Property(property="name",             type="string"),
     *             @OA\Property(property="description",      type="string"),
     *             @OA\Property(property="featured_image",   type="string",  format="binary"),
     *             @OA\Property(property="color",            type="string"),
     *             @OA\Property(property="sort_order",       type="integer"),
     *             @OA\Property(property="is_active",        type="boolean"),
     *             @OA\Property(property="meta_title",       type="string"),
     *             @OA\Property(property="meta_description", type="string"),
     *             @OA\Property(property="og_image",         type="string",  format="binary")
     *         )
     *     )),
     *     @OA\Response(response=200, description="Category updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function categoryUpdate(Request $request, $id)
    {
        $category = BlogCategory::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'slug'             => 'sometimes|string|unique:blog_categories,slug,' . $id . '|max:120',
            'description'      => 'nullable|string|max:1000',
            'featured_image'   => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'color'            => 'nullable|string|max:20',
            'sort_order'       => 'nullable|integer',
            'is_active'        => 'nullable',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image'         => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        // Handle Image Uploads
        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $this->handleFileUpload($request->file('featured_image'), 'blog/categories');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = $this->handleFileUpload($request->file('og_image'), 'blog/seo');
        }

        $category->update($validated);

        // Sync to seo_configs table
        if ($request->filled('meta_title') || $request->filled('meta_description') || $request->filled('slug')) {
            \App\Models\SeoConfig::updateOrCreate(
                ['model_type' => BlogCategory::class, 'model_id' => $category->id],
                [
                    'route_slug'       => 'blog-category-' . $category->slug,
                    'route_path'       => '/blog/category/' . $category->slug,
                    'route_label'      => 'Blog Category: ' . $category->name,
                    'route_group'      => 'Blog',
                    'meta_title'       => $request->meta_title ?? $category->meta_title ?? ($category->name . ' — Blog'),
                    'meta_description' => $request->meta_description ?? $category->meta_description ?? $category->description,
                    'og_image'         => $category->og_image,
                    'is_active'        => true,
                ]
            );
        }

        Cache::forget('blog_categories');
        Cache::forget("blog_category_{$category->slug}");

        return response()->json(['success' => true, 'data' => $category->fresh()]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/blog/categories/{id}",
     *     summary="Delete a blog category",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function categoryDestroy($id)
    {
        $category = BlogCategory::findOrFail($id);
        Cache::forget('blog_categories');
        Cache::forget("blog_category_{$category->slug}");
        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted. Posts in this category are unlinked.']);
    }

    // =========================================================
    // POSTS
    // =========================================================

    /**
     * @OA\Get(
     *     path="/admin/blog/posts",
     *     summary="List all blog posts (admin view — includes drafts)",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status",           in="query", @OA\Schema(type="string", enum={"draft","published","scheduled","archived"})),
     *     @OA\Parameter(name="blog_category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search",           in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_featured",      in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="per_page",         in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Paginated post list")
     * )
     */
    public function postIndex(Request $request)
    {
        $posts = BlogPost::withTrashed()
            ->with(['category:id,name,slug,color', 'author:id,first_name,last_name'])
            ->when($request->status,           fn ($q) => $q->where('status', $request->status))
            ->when($request->blog_category_id, fn ($q) => $q->where('blog_category_id', $request->blog_category_id))
            ->when($request->is_featured,      fn ($q) => $q->where('is_featured', true))
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('title', 'like', "%{$request->search}%")
                ->orWhere('slug',  'like', "%{$request->search}%")
                ->orWhereJsonContains('tags', $request->search)
            ))
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * @OA\Post(
     *     path="/admin/blog/posts",
     *     summary="Create a blog post",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"title"},
     *             @OA\Property(property="title",            type="string",  example="Top 10 Beach Destinations for 2027"),
     *             @OA\Property(property="slug",             type="string",  example="top-10-beach-destinations-2027"),
     *             @OA\Property(property="blog_category_id", type="integer", example=1),
     *             @OA\Property(property="excerpt",          type="string"),
     *             @OA\Property(property="content",          type="string"),
     *             @OA\Property(property="featured_image",   type="string",  format="binary"),
     *             @OA\Property(property="gallery[]",        type="array",   @OA\Items(type="string", format="binary")),
     *             @OA\Property(property="tags[]",           type="array",   @OA\Items(type="string"), example={"beach","travel"}),
     *             @OA\Property(property="status",           type="string",  enum={"draft","published","scheduled","archived"}),
     *             @OA\Property(property="published_at",     type="string",  format="date-time"),
     *             @OA\Property(property="is_featured",      type="boolean"),
     *             @OA\Property(property="allow_comments",   type="boolean"),
     *             @OA\Property(property="meta_title",       type="string"),
     *             @OA\Property(property="meta_description", type="string"),
     *             @OA\Property(property="og_title",         type="string"),
     *             @OA\Property(property="og_description",   type="string"),
     *             @OA\Property(property="og_image",         type="string",  format="binary"),
     *             @OA\Property(property="twitter_title",    type="string"),
     *             @OA\Property(property="twitter_description", type="string"),
     *             @OA\Property(property="twitter_image",    type="string",  format="binary"),
     *             @OA\Property(property="no_index",         type="boolean")
     *         )
     *     )),
     *     @OA\Response(response=201, description="Post created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function postStore(Request $request)
    {
        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'slug'                => 'nullable|string|unique:blog_posts,slug|max:300',
            'blog_category_id'    => 'nullable|exists:blog_categories,id',
            'excerpt'             => 'nullable|string|max:500',
            'content'             => 'nullable|string',
            'featured_image'      => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'gallery'             => 'nullable|array',
            'gallery.*'           => 'image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'tags'                => 'nullable|array',
            'tags.*'              => 'string|max:50',
            'status'              => 'nullable|in:draft,published,scheduled,archived',
            'published_at'        => 'nullable|date',
            'is_featured'         => 'nullable',
            'allow_comments'      => 'nullable',
            'meta_title'          => 'nullable|string|max:255',
            'meta_description'    => 'nullable|string|max:500',
            'og_title'            => 'nullable|string|max:255',
            'og_description'      => 'nullable|string|max:500',
            'og_image'            => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'twitter_title'       => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string|max:500',
            'twitter_image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'canonical_url'       => 'nullable|url',
            'no_index'            => 'nullable',
            'schema_markup'       => 'nullable|array',
        ]);

        $validated['slug']           = $validated['slug'] ?? Str::slug($validated['title']);
        $validated['author_id']      = auth()->id();
        $validated['status']         = $validated['status'] ?? 'draft';
        $validated['is_featured']    = filter_var($request->is_featured ?? false, FILTER_VALIDATE_BOOLEAN);
        $validated['allow_comments'] = filter_var($request->allow_comments ?? true, FILTER_VALIDATE_BOOLEAN);
        $validated['no_index']       = filter_var($request->no_index ?? false, FILTER_VALIDATE_BOOLEAN);

        // Auto set published_at when publishing immediately
        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        // Handle Image Uploads
        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $this->handleFileUpload($request->file('featured_image'), 'blog/posts');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = $this->handleFileUpload($request->file('og_image'), 'blog/seo');
        }
        if ($request->hasFile('twitter_image')) {
            $validated['twitter_image'] = $this->handleFileUpload($request->file('twitter_image'), 'blog/seo');
        }

        // Handle Gallery Uploads
        if ($request->hasFile('gallery')) {
            $galleryPaths = [];
            foreach ($request->file('gallery') as $image) {
                $galleryPaths[] = $this->handleFileUpload($image, 'blog/gallery');
            }
            $validated['gallery'] = $galleryPaths;
        }

        $post = BlogPost::create($validated);

        // Sync to seo_configs table
        if ($request->filled('meta_title') || $request->filled('meta_description')) {
            \App\Models\SeoConfig::updateOrCreate(
                ['model_type' => BlogPost::class, 'model_id' => $post->id],
                [
                    'route_slug'       => 'blog-' . $post->slug,
                    'route_path'       => '/blog/' . $post->slug,
                    'route_label'      => 'Blog: ' . $post->title,
                    'route_group'      => 'Blog',
                    'meta_title'       => $request->meta_title ?? $post->title,
                    'meta_description' => $request->meta_description,
                    'og_title'         => $request->og_title,
                    'og_description'   => $request->og_description,
                    'og_image'         => $post->og_image,
                    'twitter_title'    => $request->twitter_title,
                    'twitter_description' => $request->twitter_description,
                    'twitter_image'    => $post->twitter_image,
                    'no_index'         => filter_var($request->no_index ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_active'        => true,
                ]
            );
        }

        $this->flushPostCache($post);

        return response()->json([
            'success' => true,
            'data'    => $post->load(['category:id,name,slug', 'author:id,first_name,last_name']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/blog/posts/{id}",
     *     summary="Get a single blog post (full detail with SEO)",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Post detail with resolved SEO block"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function postShow($id)
    {
        $post = BlogPost::withTrashed()
            ->with(['category:id,name,slug,color', 'author:id,first_name,last_name,profile_image'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($post->toArray(), ['seo' => $post->seo]),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/admin/blog/posts/{id}",
     *     summary="Update a blog post",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(property="_method",          type="string",  example="PUT", description="Method spoofing for file uploads"),
     *             @OA\Property(property="title",            type="string"),
     *             @OA\Property(property="blog_category_id", type="integer"),
     *             @OA\Property(property="excerpt",          type="string"),
     *             @OA\Property(property="content",          type="string"),
     *             @OA\Property(property="featured_image",   type="string",  format="binary"),
     *             @OA\Property(property="gallery[]",        type="array",   @OA\Items(type="string", format="binary")),
     *             @OA\Property(property="tags[]",           type="array",   @OA\Items(type="string")),
     *             @OA\Property(property="status",           type="string",  enum={"draft","published","scheduled","archived"}),
     *             @OA\Property(property="published_at",     type="string",  format="date-time"),
     *             @OA\Property(property="is_featured",      type="boolean"),
     *             @OA\Property(property="allow_comments",   type="boolean"),
     *             @OA\Property(property="meta_title",       type="string"),
     *             @OA\Property(property="meta_description", type="string"),
     *             @OA\Property(property="og_title",         type="string"),
     *             @OA\Property(property="og_description",   type="string"),
     *             @OA\Property(property="og_image",         type="string",  format="binary"),
     *             @OA\Property(property="twitter_title",    type="string"),
     *             @OA\Property(property="twitter_description", type="string"),
     *             @OA\Property(property="twitter_image",    type="string",  format="binary"),
     *             @OA\Property(property="no_index",         type="boolean")
     *         )
     *     )),
     *     @OA\Response(response=200, description="Post updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function postUpdate(Request $request, $id)
    {
        $post = BlogPost::findOrFail($id);

        $validated = $request->validate([
            'title'               => 'sometimes|string|max:255',
            'blog_category_id'    => 'nullable|exists:blog_categories,id',
            'excerpt'             => 'nullable|string|max:500',
            'content'             => 'nullable|string',
            'featured_image'      => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'gallery'             => 'nullable|array',
            'gallery.*'           => 'image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'tags'                => 'nullable|array',
            'tags.*'              => 'string|max:50',
            'status'              => 'nullable|in:draft,published,scheduled,archived',
            'published_at'        => 'nullable|date',
            'is_featured'         => 'nullable',
            'allow_comments'      => 'nullable',
            'meta_title'          => 'nullable|string|max:255',
            'meta_description'    => 'nullable|string|max:500',
            'og_title'            => 'nullable|string|max:255',
            'og_description'      => 'nullable|string|max:500',
            'og_image'            => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'twitter_title'       => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string|max:500',
            'twitter_image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'canonical_url'       => 'nullable|url',
            'no_index'            => 'nullable',
            'schema_markup'       => 'nullable|array',
        ]);

        if (isset($validated['is_featured'])) {
            $validated['is_featured'] = filter_var($validated['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($validated['allow_comments'])) {
            $validated['allow_comments'] = filter_var($validated['allow_comments'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($validated['no_index'])) {
            $validated['no_index'] = filter_var($validated['no_index'], FILTER_VALIDATE_BOOLEAN);
        }

        // Auto set published_at when publishing for the first time
        if (isset($validated['status']) && $validated['status'] === 'published' && !$post->published_at) {
            $validated['published_at'] = $validated['published_at'] ?? now();
        }

        // Handle Image Uploads
        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $this->handleFileUpload($request->file('featured_image'), 'blog/posts');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = $this->handleFileUpload($request->file('og_image'), 'blog/seo');
        }
        if ($request->hasFile('twitter_image')) {
            $validated['twitter_image'] = $this->handleFileUpload($request->file('twitter_image'), 'blog/seo');
        }

        // Handle Gallery Uploads
        if ($request->hasFile('gallery')) {
            $galleryPaths = [];
            foreach ($request->file('gallery') as $image) {
                $galleryPaths[] = $this->handleFileUpload($image, 'blog/gallery');
            }
            $validated['gallery'] = $galleryPaths;
        }

        $post->update($validated);

        // Sync to seo_configs table
        if ($request->filled('meta_title') || $request->filled('meta_description') || $request->filled('slug')) {
            \App\Models\SeoConfig::updateOrCreate(
                ['model_type' => BlogPost::class, 'model_id' => $post->id],
                [
                    'route_slug'       => 'blog-' . $post->slug,
                    'route_path'       => '/blog/' . $post->slug,
                    'route_label'      => 'Blog: ' . $post->title,
                    'route_group'      => 'Blog',
                    'meta_title'       => $request->meta_title ?? $post->meta_title ?? $post->title,
                    'meta_description' => $request->meta_description ?? $post->meta_description,
                    'og_title'         => $request->og_title ?? $post->og_title,
                    'og_description'   => $request->og_description ?? $post->og_description,
                    'og_image'         => $post->og_image,
                    'twitter_title'    => $request->twitter_title ?? $post->twitter_title,
                    'twitter_description' => $request->twitter_description ?? $post->twitter_description,
                    'twitter_image'    => $post->twitter_image,
                    'no_index'         => filter_var($request->no_index ?? $post->no_index, FILTER_VALIDATE_BOOLEAN),
                    'is_active'        => true,
                ]
            );
        }

        $this->flushPostCache($post);

        return response()->json([
            'success' => true,
            'data'    => array_merge($post->fresh()->load(['category:id,name,slug', 'author:id,first_name,last_name'])->toArray(), ['seo' => $post->fresh()->seo]),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/blog/posts/{id}",
     *     summary="Soft-delete a blog post",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Post deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function postDestroy($id)
    {
        $post = BlogPost::findOrFail($id);
        $this->flushPostCache($post);
        $post->delete();

        return response()->json(['success' => true, 'message' => 'Post deleted.']);
    }

    /**
     * @OA\Put(
     *     path="/admin/blog/posts/{id}/restore",
     *     summary="Restore a soft-deleted blog post",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Post restored")
     * )
     */
    public function postRestore($id)
    {
        $post = BlogPost::withTrashed()->findOrFail($id);
        $post->restore();
        $this->flushPostCache($post);

        return response()->json(['success' => true, 'data' => $post]);
    }

    /**
     * @OA\Put(
     *     path="/admin/blog/posts/{id}/toggle-featured",
     *     summary="Toggle featured status of a post",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Featured status toggled")
     * )
     */
    public function postToggleFeatured($id)
    {
        $post = BlogPost::findOrFail($id);
        $post->update(['is_featured' => !$post->is_featured]);
        $this->flushPostCache($post);

        return response()->json(['success' => true, 'is_featured' => $post->fresh()->is_featured]);
    }

    /**
     * @OA\Put(
     *     path="/admin/blog/posts/{id}/status",
     *     summary="Update post publish status",
     *     tags={"Admin - Blog"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status"},
     *         @OA\Property(property="status",       type="string", enum={"draft","published","scheduled","archived"}),
     *         @OA\Property(property="published_at", type="string", format="date-time", description="Required when status=scheduled")
     *     )),
     *     @OA\Response(response=200, description="Status updated")
     * )
     */
    public function postUpdateStatus(Request $request, $id)
    {
        $post = BlogPost::findOrFail($id);

        $request->validate([
            'status'       => 'required|in:draft,published,scheduled,archived',
            'published_at' => 'required_if:status,scheduled|nullable|date',
        ]);

        $updates = ['status' => $request->status];

        if ($request->status === 'published' && !$post->published_at) {
            $updates['published_at'] = now();
        }
        if ($request->status === 'scheduled') {
            $updates['published_at'] = $request->published_at;
        }

        $post->update($updates);
        $this->flushPostCache($post);

        return response()->json(['success' => true, 'message' => "Post status set to {$request->status}.", 'data' => $post->fresh()]);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function handleFileUpload($file, $folder)
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');
        return '/storage/' . $path;
    }

    private function flushPostCache(BlogPost $post): void
    {
        Cache::forget("blog_post_{$post->slug}");
        Cache::forget("seo_blog-{$post->slug}");
        Cache::forget('blog_featured');
        Cache::forget('blog_recent');
        if ($post->blog_category_id) {
            Cache::forget("blog_category_{$post->blog_category_id}");
        }
    }
}
