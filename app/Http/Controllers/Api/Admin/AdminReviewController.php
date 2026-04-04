<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin - Reviews",
 *     description="Admin review moderation endpoints"
 * )
 */
class AdminReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/reviews",
     *     summary="List all reviews with optional status filter",
     *     tags={"Admin - Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","approved","rejected"})),
     *     @OA\Parameter(name="property_code", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Paginated review list")
     * )
     */
    public function index(Request $request)
    {
        $reviews = Review::with('user:id,first_name,last_name,email')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->property_code, fn ($q) => $q->where('property_code', $request->property_code))
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    /**
     * @OA\Put(
     *     path="/admin/reviews/{id}/approve",
     *     summary="Approve a review",
     *     tags={"Admin - Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review approved"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function approve($id)
    {
        $review = Review::findOrFail($id);
        $review->update(['status' => 'approved']);
        return response()->json(['success' => true, 'message' => 'Review approved.']);
    }

    /**
     * @OA\Put(
     *     path="/admin/reviews/{id}/reject",
     *     summary="Reject a review",
     *     tags={"Admin - Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review rejected"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function reject($id)
    {
        $review = Review::findOrFail($id);
        $review->update(['status' => 'rejected']);
        return response()->json(['success' => true, 'message' => 'Review rejected.']);
    }

    /**
     * @OA\Put(
     *     path="/admin/reviews/{id}/reply",
     *     summary="Add admin reply to a review",
     *     tags={"Admin - Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"reply"},
     *         @OA\Property(property="reply", type="string", example="Thank you for your feedback!")
     *     )),
     *     @OA\Response(response=200, description="Reply added"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function reply(Request $request, $id)
    {
        $request->validate(['reply' => 'required|string|max:1000']);
        $review = Review::findOrFail($id);
        $review->update(['admin_reply' => $request->reply, 'admin_replied_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Reply added.', 'data' => $review]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/reviews/{id}",
     *     summary="Delete a review",
     *     tags={"Admin - Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        Review::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Review deleted.']);
    }
}
