<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin Content",
 *     description="Content management endpoints"
 * )
 */
class AdminContentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/content",
     *     summary="List content pages",
     *     tags={"Admin Content"},
     *     @OA\Response(response=200, description="List of content pages")
     * )
     */
    public function index(Request $request)
    {
        $query = ContentPage::query();
        
        if ($request->type) {
            $query->where('type', $request->type);
        }
        
        return response()->json([
            'success' => true,
            'data' => $query->paginate(20)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/content",
     *     summary="Create content page",
     *     tags={"Admin Content"},
     *     @OA\Response(response=201, description="Content created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'type' => 'required|in:page,header,footer,section',
            'content' => 'nullable',
            'slug' => 'nullable|string|unique:content_pages,slug',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);
        
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $content = ContentPage::create($validated);
        
        return response()->json([
            'success' => true,
            'data' => $content
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/content/{id}",
     *     summary="Get content page",
     *     tags={"Admin Content"},
     *     @OA\Response(response=200, description="Content details")
     * )
     */
    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => ContentPage::findOrFail($id)
        ]);
    }

    /**
     * @OA\Put(
     *     path="/admin/content/{id}",
     *     summary="Update content page",
     *     tags={"Admin Content"},
     *     @OA\Response(response=200, description="Content updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $content = ContentPage::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'string',
            'type' => 'in:page,header,footer,section',
            'content' => 'nullable',
            'slug' => 'string|unique:content_pages,slug,' . $id,
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $content->update($validated);
        
        return response()->json([
            'success' => true,
            'data' => $content
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/content/{id}",
     *     summary="Delete content page",
     *     tags={"Admin Content"},
     *     @OA\Response(response=200, description="Content deleted")
     * )
     */
    public function destroy($id)
    {
        ContentPage::destroy($id);
        
        return response()->json([
            'success' => true,
            'message' => 'Content deleted successfully'
        ]);
    }
}
