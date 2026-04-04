<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Content",
 *     description="Public content endpoints"
 * )
 */
class ContentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/public/content/pages/{slug}",
     *     summary="Get public page content",
     *     tags={"Content"},
     *     @OA\Response(response=200, description="Page content")
     * )
     */
    public function getPage($slug)
    {
        $page = ContentPage::where('slug', $slug)
            ->where('type', 'page')
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $page
        ]);
    }

    /**
     * @OA\Get(
     *     path="/public/content/header",
     *     summary="Get header content",
     *     tags={"Content"},
     *     @OA\Response(response=200, description="Header content")
     * )
     */
    public function getHeader()
    {
        $content = ContentPage::where('type', 'header')
            ->where('is_active', true)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $content
        ]);
    }

    /**
     * @OA\Get(
     *     path="/public/content/footer",
     *     summary="Get footer content",
     *     tags={"Content"},
     *     @OA\Response(response=200, description="Footer content")
     * )
     */
    public function getFooter()
    {
        $content = ContentPage::where('type', 'footer')
            ->where('is_active', true)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $content
        ]);
    }
}
