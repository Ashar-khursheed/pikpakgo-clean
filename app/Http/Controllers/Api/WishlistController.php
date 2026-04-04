<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Wishlist",
 *     description="User saved/favourite properties"
 * )
 */
class WishlistController extends Controller
{
    /**
     * @OA\Get(
     *     path="/wishlist",
     *     summary="Get current user's wishlist",
     *     tags={"Wishlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Wishlist items"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $items = Wishlist::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * @OA\Post(
     *     path="/wishlist",
     *     summary="Add a property to wishlist",
     *     tags={"Wishlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"property_code","property_name"},
     *         @OA\Property(property="property_code", type="string", example="orp5b27f9x"),
     *         @OA\Property(property="property_name", type="string", example="Beach House"),
     *         @OA\Property(property="provider", type="string", example="ownerrez"),
     *         @OA\Property(property="property_city", type="string", example="Miami"),
     *         @OA\Property(property="property_country", type="string", example="US"),
     *         @OA\Property(property="featured_image", type="string", example="https://..."),
     *         @OA\Property(property="price_from", type="number", example=150.00),
     *         @OA\Property(property="currency", type="string", example="USD")
     *     )),
     *     @OA\Response(response=201, description="Added to wishlist"),
     *     @OA\Response(response=200, description="Already in wishlist"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_code'    => 'required|string|max:100',
            'property_name'    => 'required|string|max:255',
            'provider'         => 'sometimes|string|max:50',
            'property_city'    => 'sometimes|nullable|string|max:100',
            'property_country' => 'sometimes|nullable|string|max:100',
            'featured_image'   => 'sometimes|nullable|string|max:500',
            'price_from'       => 'sometimes|nullable|numeric|min:0',
            'currency'         => 'sometimes|string|size:3',
        ]);

        $item = Wishlist::firstOrCreate(
            ['user_id' => auth()->id(), 'property_code' => $validated['property_code']],
            array_merge($validated, ['user_id' => auth()->id()])
        );

        $statusCode = $item->wasRecentlyCreated ? 201 : 200;
        $message    = $item->wasRecentlyCreated ? 'Added to wishlist.' : 'Already in your wishlist.';

        return response()->json(['success' => true, 'message' => $message, 'data' => $item], $statusCode);
    }

    /**
     * @OA\Delete(
     *     path="/wishlist/{propertyCode}",
     *     summary="Remove a property from wishlist",
     *     tags={"Wishlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyCode", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Removed from wishlist"),
     *     @OA\Response(response=404, description="Not in wishlist")
     * )
     */
    public function destroy($propertyCode)
    {
        $item = Wishlist::where('user_id', auth()->id())
            ->where('property_code', $propertyCode)
            ->firstOrFail();

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Removed from wishlist.']);
    }

    /**
     * @OA\Get(
     *     path="/wishlist/check/{propertyCode}",
     *     summary="Check if a property is in the user's wishlist",
     *     tags={"Wishlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyCode", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Saved status")
     * )
     */
    public function check($propertyCode)
    {
        $saved = Wishlist::where('user_id', auth()->id())
            ->where('property_code', $propertyCode)
            ->exists();

        return response()->json(['success' => true, 'data' => ['saved' => $saved]]);
    }
}
