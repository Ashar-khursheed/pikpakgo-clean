<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyListing;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Admin Property Approvals",
 *     description="Workflow for approving/rejecting properties (Admin only)"
 * )
 */
class PropertyApprovalController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/properties/approvals/pending",
     *     summary="List properties pending approval",
     *     tags={"Admin Property Approvals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Paginated list of pending properties")
     * )
     */
    public function pending(): JsonResponse
    {
        $properties = PropertyListing::pending()->paginate(20);
        
        return response()->json([
            'status' => 'success',
            'data' => $properties
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/properties/approvals/{id}/approve",
     *     summary="Approve a property",
     *     tags={"Admin Property Approvals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Property approved")
     * )
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $property = PropertyListing::findOrFail($id);
        
        $property->update([
            'approval_status' => PropertyListing::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'is_active' => true // Automatically activate on approval
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Property approved successfully',
            'data' => $property
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/properties/approvals/{id}/reject",
     *     summary="Reject a property",
     *     tags={"Admin Property Approvals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Missing high-quality images")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Property rejected")
     * )
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);
        
        $property = PropertyListing::findOrFail($id);
        
        $property->update([
            'approval_status' => PropertyListing::STATUS_REJECTED,
            'rejection_reason' => $request->reason,
            'is_active' => false // Ensure it's not active if rejected
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Property rejected',
            'data' => $property
        ]);
    }
}
