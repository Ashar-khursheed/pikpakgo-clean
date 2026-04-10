<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyFee;
use App\Models\PropertyListing;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Property Fees",
 *     description="Manage cleaning fees, service fees, taxes, and damage deposits per property"
 * )
 */
class AdminPropertyFeesController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/properties/{propertyId}/fees",
     *     summary="List all fees for a property",
     *     tags={"Admin Property Fees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Property fees list"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function index($propertyId)
    {
        $property = PropertyListing::findOrFail($propertyId);
        $fees = $property->fees()->orderBy('fee_type')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'property' => $property->only(['id', 'name', 'provider_property_id']),
                'fees'     => $fees,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/properties/{propertyId}/fees",
     *     summary="Add a fee to a property",
     *     tags={"Admin Property Fees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fee_type","fee_name","amount"},
     *             @OA\Property(property="fee_type", type="string", enum={"cleaning_fee","service_fee","city_tax","damage_deposit","other"}, example="cleaning_fee"),
     *             @OA\Property(property="fee_name", type="string", example="Cleaning Fee"),
     *             @OA\Property(property="amount", type="number", example=75.00),
     *             @OA\Property(property="amount_type", type="string", enum={"fixed","percentage"}, example="fixed"),
     *             @OA\Property(property="applies_to", type="string", enum={"per_stay","per_night","per_guest"}, example="per_stay"),
     *             @OA\Property(property="is_mandatory", type="boolean", example=true),
     *             @OA\Property(property="is_taxable", type="boolean", example=false),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Fee created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $propertyId)
    {
        $property = PropertyListing::findOrFail($propertyId);

        $validated = $request->validate([
            'fee_type'     => 'required|in:cleaning_fee,service_fee,city_tax,damage_deposit,other',
            'fee_name'     => 'required|string|max:100',
            'amount'       => 'required|numeric|min:0',
            'amount_type'  => 'sometimes|in:fixed,percentage',
            'applies_to'   => 'sometimes|in:per_stay,per_night,per_guest',
            'is_mandatory' => 'sometimes|boolean',
            'is_taxable'   => 'sometimes|boolean',
            'is_active'    => 'sometimes|boolean',
        ]);

        $fee = $property->fees()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fee added successfully.',
            'data'    => $fee,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/properties/{propertyId}/fees/{feeId}",
     *     summary="Update a property fee",
     *     tags={"Admin Property Fees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="feeId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="fee_name", type="string"),
     *             @OA\Property(property="amount", type="number"),
     *             @OA\Property(property="amount_type", type="string", enum={"fixed","percentage"}),
     *             @OA\Property(property="applies_to", type="string", enum={"per_stay","per_night","per_guest"}),
     *             @OA\Property(property="is_mandatory", type="boolean"),
     *             @OA\Property(property="is_taxable", type="boolean"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Fee updated"),
     *     @OA\Response(response=404, description="Fee not found")
     * )
     */
    public function update(Request $request, $propertyId, $feeId)
    {
        $fee = PropertyFee::where('property_listing_id', $propertyId)->findOrFail($feeId);

        $validated = $request->validate([
            'fee_type'     => 'sometimes|in:cleaning_fee,service_fee,city_tax,damage_deposit,other',
            'fee_name'     => 'sometimes|string|max:100',
            'amount'       => 'sometimes|numeric|min:0',
            'amount_type'  => 'sometimes|in:fixed,percentage',
            'applies_to'   => 'sometimes|in:per_stay,per_night,per_guest',
            'is_mandatory' => 'sometimes|boolean',
            'is_taxable'   => 'sometimes|boolean',
            'is_active'    => 'sometimes|boolean',
        ]);

        $fee->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fee updated successfully.',
            'data'    => $fee->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/properties/{propertyId}/fees/{feeId}",
     *     summary="Delete a property fee",
     *     tags={"Admin Property Fees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="feeId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fee deleted"),
     *     @OA\Response(response=404, description="Fee not found")
     * )
     */
    public function destroy($propertyId, $feeId)
    {
        $fee = PropertyFee::where('property_listing_id', $propertyId)->findOrFail($feeId);
        $fee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fee deleted successfully.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/properties/{propertyId}/fees/bulk",
     *     summary="Bulk replace all fees for a property",
     *     tags={"Admin Property Fees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fees"},
     *             @OA\Property(property="fees", type="array", @OA\Items(
     *                 @OA\Property(property="fee_type", type="string"),
     *                 @OA\Property(property="fee_name", type="string"),
     *                 @OA\Property(property="amount", type="number"),
     *                 @OA\Property(property="amount_type", type="string"),
     *                 @OA\Property(property="applies_to", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Fees replaced"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkReplace(Request $request, $propertyId)
    {
        $property = PropertyListing::findOrFail($propertyId);

        $validated = $request->validate([
            'fees'                 => 'required|array',
            'fees.*.fee_type'      => 'required|in:cleaning_fee,service_fee,city_tax,damage_deposit,other',
            'fees.*.fee_name'      => 'required|string|max:100',
            'fees.*.amount'        => 'required|numeric|min:0',
            'fees.*.amount_type'   => 'sometimes|in:fixed,percentage',
            'fees.*.applies_to'    => 'sometimes|in:per_stay,per_night,per_guest',
            'fees.*.is_mandatory'  => 'sometimes|boolean',
            'fees.*.is_taxable'    => 'sometimes|boolean',
        ]);

        // Delete all existing fees and recreate
        $property->fees()->delete();

        $created = [];
        foreach ($validated['fees'] as $feeData) {
            $created[] = $property->fees()->create($feeData);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' fee(s) saved successfully.',
            'data'    => $created,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/properties/{propertyId}/fees/preview",
     *     summary="Preview fee calculation for a booking",
     *     tags={"Admin Property Fees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="propertyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="base_price", in="query", required=true, @OA\Schema(type="number")),
     *     @OA\Parameter(name="nights", in="query", @OA\Schema(type="integer", example=3)),
     *     @OA\Parameter(name="guests", in="query", @OA\Schema(type="integer", example=2)),
     *     @OA\Response(response=200, description="Fee preview with totals")
     * )
     */
    public function preview(Request $request, $propertyId)
    {
        $property = PropertyListing::findOrFail($propertyId);

        $validated = $request->validate([
            'base_price' => 'required|numeric|min:0',
            'nights'     => 'sometimes|integer|min:1',
            'guests'     => 'sometimes|integer|min:1',
        ]);

        $basePrice = (float) $validated['base_price'];
        $nights    = (int) ($validated['nights'] ?? 1);
        $guests    = (int) ($validated['guests'] ?? 1);

        $fees = $property->fees()->active()->get();
        $breakdown = [];
        $total = 0;

        foreach ($fees as $fee) {
            $amount = $fee->calculate($basePrice, $nights, $guests);
            $total += $fee->is_mandatory ? $amount : 0;
            $breakdown[] = [
                'fee_type'    => $fee->fee_type,
                'fee_name'    => $fee->fee_name,
                'calculated'  => $amount,
                'is_mandatory' => $fee->is_mandatory,
                'is_taxable'  => $fee->is_taxable,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'base_price'     => $basePrice,
                'nights'         => $nights,
                'guests'         => $guests,
                'fees_breakdown' => $breakdown,
                'total_fees'     => round($total, 2),
                'grand_total'    => round($basePrice + $total, 2),
            ],
        ]);
    }
}
