<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Reviews",
 *     description="Property reviews and ratings"
 * )
 */
class ReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/public/reviews/{propertyCode}",
     *     summary="Get approved reviews for a property",
     *     tags={"Reviews"},
     *     @OA\Parameter(name="propertyCode", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=10)),
     *     @OA\Response(response=200, description="Reviews list with average rating")
     * )
     */
    public function index($propertyCode)
    {
        $reviews = Review::with('user:id,first_name,last_name')
            ->where('property_code', $propertyCode)
            ->where('status', 'approved')
            ->orderByDesc('is_verified')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $avg = Review::where('property_code', $propertyCode)
            ->where('status', 'approved')
            ->avg('rating');

        $verifiedCount = Review::where('property_code', $propertyCode)
            ->where('status', 'approved')
            ->where('is_verified', true)
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'reviews'         => $reviews,
                'average_rating'  => $avg ? round($avg, 1) : null,
                'total_reviews'   => $reviews->total(),
                'verified_reviews' => $verifiedCount,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/reviews",
     *     summary="Submit a review for a property",
     *     tags={"Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"property_code","property_name","rating"},
     *         @OA\Property(property="property_code", type="string", example="orp5b27f9x"),
     *         @OA\Property(property="property_name", type="string", example="Beach House"),
     *         @OA\Property(property="provider", type="string", example="ownerrez"),
     *         @OA\Property(property="booking_reference", type="string"),
     *         @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
     *         @OA\Property(property="title", type="string", example="Amazing stay!"),
     *         @OA\Property(property="body", type="string", example="We had a great time."),
     *         @OA\Property(property="cleanliness_rating", type="integer", minimum=1, maximum=5),
     *         @OA\Property(property="accuracy_rating", type="integer", minimum=1, maximum=5),
     *         @OA\Property(property="communication_rating", type="integer", minimum=1, maximum=5),
     *         @OA\Property(property="location_rating", type="integer", minimum=1, maximum=5),
     *         @OA\Property(property="value_rating", type="integer", minimum=1, maximum=5)
     *     )),
     *     @OA\Response(response=201, description="Review submitted for approval"),
     *     @OA\Response(response=422, description="Validation error or duplicate review")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_code'        => 'required|string|max:100',
            'property_name'        => 'required|string|max:255',
            'provider'             => 'sometimes|string|max:50',
            'booking_reference'    => 'sometimes|nullable|string|max:100',
            'rating'               => 'required|integer|min:1|max:5',
            'title'                => 'sometimes|nullable|string|max:200',
            'body'                 => 'sometimes|nullable|string|max:2000',
            'cleanliness_rating'   => 'sometimes|nullable|integer|min:1|max:5',
            'accuracy_rating'      => 'sometimes|nullable|integer|min:1|max:5',
            'communication_rating' => 'sometimes|nullable|integer|min:1|max:5',
            'location_rating'      => 'sometimes|nullable|integer|min:1|max:5',
            'value_rating'         => 'sometimes|nullable|integer|min:1|max:5',
        ]);

        $userId = auth()->id();

        // Check if user has a completed booking for this property (verified review)
        $completedBooking = Booking::where('user_id', $userId)
            ->where('property_code', $validated['property_code'])
            ->where('booking_status', 'confirmed')
            ->where('check_out_date', '<', now()->toDateString())
            ->orderByDesc('check_out_date')
            ->first();

        if (!$completedBooking) {
            return response()->json([
                'success' => false,
                'message' => 'You can only review properties where you have completed a stay.',
            ], 403);
        }

        // Prevent duplicate review for same property
        $existing = Review::where('user_id', $userId)
            ->where('property_code', $validated['property_code'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this property.',
            ], 422);
        }

        $review = Review::create(array_merge($validated, [
            'user_id'           => $userId,
            'booking_id'        => $completedBooking->id,
            'booking_reference' => $validated['booking_reference'] ?? $completedBooking->booking_reference,
            'status'            => 'pending',
            'is_verified'       => true,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Review submitted and pending approval.',
            'data'    => $review,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/reviews/{id}",
     *     summary="Update own review (before approval)",
     *     tags={"Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="rating", type="integer", minimum=1, maximum=5),
     *         @OA\Property(property="title", type="string"),
     *         @OA\Property(property="body", type="string")
     *     )),
     *     @OA\Response(response=200, description="Review updated"),
     *     @OA\Response(response=403, description="Not your review or already approved"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $review = Review::where('id', $id)->where('user_id', auth()->id())->firstOrFail();

        if ($review->status === 'approved') {
            return response()->json(['success' => false, 'message' => 'Approved reviews cannot be edited.'], 403);
        }

        $validated = $request->validate([
            'rating'               => 'sometimes|integer|min:1|max:5',
            'title'                => 'sometimes|nullable|string|max:200',
            'body'                 => 'sometimes|nullable|string|max:2000',
            'cleanliness_rating'   => 'sometimes|nullable|integer|min:1|max:5',
            'accuracy_rating'      => 'sometimes|nullable|integer|min:1|max:5',
            'communication_rating' => 'sometimes|nullable|integer|min:1|max:5',
            'location_rating'      => 'sometimes|nullable|integer|min:1|max:5',
            'value_rating'         => 'sometimes|nullable|integer|min:1|max:5',
        ]);

        $review->update(array_merge($validated, ['status' => 'pending']));

        return response()->json(['success' => true, 'message' => 'Review updated.', 'data' => $review]);
    }

    /**
     * @OA\Delete(
     *     path="/reviews/{id}",
     *     summary="Delete own review",
     *     tags={"Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review deleted"),
     *     @OA\Response(response=403, description="Not your review"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $review = Review::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $review->delete();
        return response()->json(['success' => true, 'message' => 'Review deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/reviews/my",
     *     summary="Get current user's reviews",
     *     tags={"Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="User's reviews")
     * )
     */
    public function myReviews()
    {
        $reviews = Review::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $reviews]);
    }
}
