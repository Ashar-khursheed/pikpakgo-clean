<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Public - Blog",
 *     description="Public blog — posts, categories, featured articles, search"
 * )
 */
class BlogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/public/blog/posts",
     *     summary="List published blog posts",
     *     tags={"Public - Blog"},
     *     @OA\Parameter(name="category",    in="query", description="Category slug", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tag",         in="query", description="Tag filter", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search",      in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="featured",    in="query", description="Only featured posts", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", example=12)),
     *     @OA\Response(response=200, description="Paginated published posts")
     * )
     */
    public function index(Request $request)
    {
        $posts = BlogPost::published()
            ->with(['category:id,name,slug,color', 'author:id,first_name,last_name,profile_image'])
            ->when($request->category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->category)))
            ->when($request->tag,      fn ($q) => $q->whereJsonContains('tags', $request->tag))
            ->when($request->featured, fn ($q) => $q->featured())
            ->when($request->search,   fn ($q) => $q->where(fn ($q2) => $q2
                ->where('title',   'like', "%{$request->search}%")
                ->orWhere('excerpt','like', "%{$request->search}%")
                ->orWhereJsonContains('tags', $request->search)
            ))
            ->select([
                'id','slug','title','excerpt','featured_image','tags',
                'blog_category_id','author_id','published_at','read_time',
                'is_featured','view_count',
            ])
            ->orderByDesc('published_at')
            ->paginate((int) $request->get('per_page', 12));

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * @OA\Get(
     *     path="/public/blog/posts/{slug}",
     *     summary="Get a single published blog post with full content and SEO",
     *     tags={"Public - Blog"},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string", example="top-10-beach-destinations-2027")),
     *     @OA\Response(response=200, description="Post detail with SEO block"),
     *     @OA\Response(response=404, description="Post not found or not published")
     * )
     */
    public function show($slug)
    {
        $post = Cache::remember("blog_post_{$slug}", 600, fn () =>
            BlogPost::published()
                ->with(['category:id,name,slug,color', 'author:id,first_name,last_name,profile_image'])
                ->where('slug', $slug)
                ->first()
        );

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        // Increment view count (non-blocking)
        BlogPost::where('id', $post->id)->increment('view_count');

        // Related posts (same category, exclude current)
        $related = BlogPost::published()
            ->where('blog_category_id', $post->blog_category_id)
            ->where('id', '!=', $post->id)
            ->select(['id','slug','title','excerpt','featured_image','published_at','read_time'])
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => array_merge($post->toArray(), ['seo' => $post->seo]),
            'related' => $related,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/public/blog/categories",
     *     summary="List all active blog categories with post counts",
     *     tags={"Public - Blog"},
     *     @OA\Response(response=200, description="Category list")
     * )
     */
    public function categories()
    {
        $categories = Cache::remember('blog_categories', 3600, fn () =>
            BlogCategory::where('is_active', true)
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * @OA\Get(
     *     path="/public/blog/categories/{slug}",
     *     summary="Get a category with its published posts and SEO",
     *     tags={"Public - Blog"},
     *     @OA\Parameter(name="slug",     in="path",  required=true, @OA\Schema(type="string", example="travel-tips")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=12)),
     *     @OA\Response(response=200, description="Category detail with posts and SEO"),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function categoryShow($slug, Request $request)
    {
        $category = Cache::remember("blog_category_{$slug}", 600, fn () =>
            BlogCategory::where('slug', $slug)->where('is_active', true)->first()
        );

        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        $posts = BlogPost::published()
            ->where('blog_category_id', $category->id)
            ->with(['author:id,first_name,last_name,profile_image'])
            ->select(['id','slug','title','excerpt','featured_image','tags','author_id','published_at','read_time','view_count'])
            ->orderByDesc('published_at')
            ->paginate((int) $request->get('per_page', 12));

        return response()->json([
            'success'  => true,
            'category' => array_merge($category->toArray(), ['seo' => $category->seo]),
            'data'     => $posts,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/public/blog/featured",
     *     summary="Get featured blog posts",
     *     tags={"Public - Blog"},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", example=6)),
     *     @OA\Response(response=200, description="Featured posts")
     * )
     */
    public function featured(Request $request)
    {
        $limit = min((int) $request->get('limit', 6), 20);

        $posts = Cache::remember("blog_featured_{$limit}", 600, fn () =>
            BlogPost::published()
                ->featured()
                ->with(['category:id,name,slug,color', 'author:id,first_name,last_name'])
                ->select(['id','slug','title','excerpt','featured_image','tags','blog_category_id','author_id','published_at','read_time'])
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get()
        );

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * @OA\Get(
     *     path="/public/blog/tags",
     *     summary="Get all tags used in published posts",
     *     tags={"Public - Blog"},
     *     @OA\Response(response=200, description="Flat tag list with usage count")
     * )
     */
    public function tags()
    {
        $tags = Cache::remember('blog_tags', 3600, function () {
            $rows = BlogPost::published()->whereNotNull('tags')->pluck('tags');
            $flat = collect($rows)->flatten()->filter()->map(fn ($t) => strtolower(trim($t)));
            return $flat->countBy()->sortDesc()->take(50);
        });

        return response()->json(['success' => true, 'data' => $tags]);
    }

    /**
     * @OA\Get(
     *     path="/public/blog/recent",
     *     summary="Get most recent published posts",
     *     tags={"Public - Blog"},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", example=5)),
     *     @OA\Response(response=200, description="Recent posts")
     * )
     */
    public function recent(Request $request)
    {
        $limit = min((int) $request->get('limit', 5), 20);

        $posts = Cache::remember("blog_recent_{$limit}", 300, fn () =>
            BlogPost::published()
                ->with(['category:id,name,slug,color'])
                ->select(['id','slug','title','featured_image','blog_category_id','published_at','read_time'])
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get()
        );

        return response()->json(['success' => true, 'data' => $posts]);
    }
}
