<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingMarkup;
use App\Services\PricingMarkupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin - Pricing Markups",
 *     description="Manage pricing markup rules applied to booking prices"
 * )
 */
class PricingMarkupController extends Controller
{
    protected $pricingService;

    public function __construct(PricingMarkupService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * @OA\Get(
     *     path="/admin/pricing-markups",
     *     summary="List all pricing markup rules",
     *     tags={"Admin - Pricing Markups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="provider", in="query", @OA\Schema(type="string", enum={"ownerrez","hotelbeds","all"})),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Paginated markup list")
     * )
     */
    public function index(Request $request)
    {
        $query = PricingMarkup::query();
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        if ($request->has('provider')) {
            $query->provider($request->provider);
        }
        
        $markups = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);
        
        return response()->json([
            'success' => true,
            'data' => $markups
        ]);
    }
    
    /**
     * @OA\Post(
     *     path="/admin/pricing-markups",
     *     summary="Create a new pricing markup rule",
     *     tags={"Admin - Pricing Markups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name","markup_type","provider"},
     *         @OA\Property(property="name", type="string", example="Standard 10% Markup"),
     *         @OA\Property(property="markup_type", type="string", enum={"percentage","fixed","tiered"}),
     *         @OA\Property(property="markup_percentage", type="number", example=10),
     *         @OA\Property(property="markup_fixed_amount", type="number", example=50),
     *         @OA\Property(property="provider", type="string", enum={"ownerrez","hotelbeds","all"}),
     *         @OA\Property(property="is_active", type="boolean", example=true),
     *         @OA\Property(property="priority", type="integer", example=1)
     *     )),
     *     @OA\Response(response=201, description="Markup created"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'markup_type' => 'required|in:percentage,fixed,tiered',
            'markup_percentage' => 'required_if:markup_type,percentage|nullable|numeric|min:0|max:100',
            'markup_fixed_amount' => 'required_if:markup_type,fixed|nullable|numeric|min:0',
            'tiered_pricing' => 'required_if:markup_type,tiered|nullable|array',
            'provider' => 'required|in:hotelbeds,ownerrez,all',
            'property_type' => 'nullable|string',
            'destination_code' => 'nullable|string',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after:valid_from',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        $markup = PricingMarkup::create([
            'name' => $request->name,
            'description' => $request->description,
            'markup_type' => $request->markup_type,
            'markup_percentage' => $request->markup_percentage,
            'markup_fixed_amount' => $request->markup_fixed_amount,
            'tiered_pricing' => $request->tiered_pricing,
            'provider' => $request->provider,
            'property_type' => $request->property_type,
            'destination_code' => $request->destination_code,
            'min_price' => $request->min_price,
            'max_price' => $request->max_price,
            'valid_from' => $request->valid_from,
            'valid_to' => $request->valid_to,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->is_active ?? true,
            'created_by' => auth()->id(),
        ]);
        
        // Clear cache
        $this->pricingService->clearCache();
        
        return response()->json([
            'success' => true,
            'message' => 'Pricing markup created successfully',
            'data' => $markup
        ], 201);
    }
    
    /**
     * @OA\Get(
     *     path="/admin/pricing-markups/{id}",
     *     summary="Get a single pricing markup",
     *     tags={"Admin - Pricing Markups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Markup details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $markup = PricingMarkup::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $markup
        ]);
    }
    
    /**
     * @OA\Put(
     *     path="/admin/pricing-markups/{id}",
     *     summary="Update a pricing markup rule",
     *     tags={"Admin - Pricing Markups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="markup_percentage", type="number"),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Markup updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $markup = PricingMarkup::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'markup_type' => 'in:percentage,fixed,tiered',
            'markup_percentage' => 'nullable|numeric|min:0|max:100',
            'markup_fixed_amount' => 'nullable|numeric|min:0',
            'tiered_pricing' => 'nullable|array',
            'provider' => 'in:hotelbeds,ownerrez,all',
            'property_type' => 'nullable|string',
            'destination_code' => 'nullable|string',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        $markup->update(array_merge(
            $request->only([
                'name', 'description', 'markup_type', 'markup_percentage',
                'markup_fixed_amount', 'tiered_pricing', 'provider',
                'property_type', 'destination_code', 'min_price', 'max_price',
                'valid_from', 'valid_to', 'priority', 'is_active'
            ]),
            ['updated_by' => auth()->id()]
        ));
        
        $this->pricingService->clearCache();
        
        return response()->json([
            'success' => true,
            'message' => 'Pricing markup updated successfully',
            'data' => $markup
        ]);
    }
    
    /**
     * @OA\Delete(
     *     path="/admin/pricing-markups/{id}",
     *     summary="Delete a pricing markup rule",
     *     tags={"Admin - Pricing Markups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $markup = PricingMarkup::findOrFail($id);
        $markup->delete();
        
        $this->pricingService->clearCache();
        
        return response()->json([
            'success' => true,
            'message' => 'Pricing markup deleted successfully'
        ]);
    }
    
    /**
     * @OA\Put(
     *     path="/admin/pricing-markups/{id}/toggle-status",
     *     summary="Toggle markup active/inactive",
     *     tags={"Admin - Pricing Markups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Status toggled")
     * )
     */
    public function toggleStatus($id)
    {
        $markup = PricingMarkup::findOrFail($id);
        $markup->update([
            'is_active' => !$markup->is_active,
            'updated_by' => auth()->id()
        ]);
        
        $this->pricingService->clearCache();
        
        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $markup
        ]);
    }
    
    /**
     * @OA\Post(
     *     path="/admin/pricing-markups/set-default",
     *     summary="Set a markup rule as the default",
     *     tags={"Admin - Pricing Markups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"markup_id"},
     *         @OA\Property(property="markup_id", type="integer", example=1)
     *     )),
     *     @OA\Response(response=200, description="Default set")
     * )
     */
    public function setDefault(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'markup_id' => 'required|exists:pricing_markups,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        // Unset current default
        PricingMarkup::where('is_default', true)->update(['is_default' => false]);
        
        // Set new default
        $markup = PricingMarkup::findOrFail($request->markup_id);
        $markup->update(['is_default' => true]);
        
        $this->pricingService->clearCache();
        
        return response()->json([
            'success' => true,
            'message' => 'Default markup set successfully',
            'data' => $markup
        ]);
    }
    
    /**
     * @OA\Post(
     *     path="/admin/pricing-markups/calculate",
     *     summary="Test markup calculator with a given base price",
     *     tags={"Admin - Pricing Markups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"base_price"},
     *         @OA\Property(property="base_price", type="number", example=500.00),
     *         @OA\Property(property="provider", type="string", example="ownerrez"),
     *         @OA\Property(property="check_in_date", type="string", format="date", example="2027-06-01")
     *     )),
     *     @OA\Response(response=200, description="Markup calculation result")
     * )
     */
    public function calculateMarkup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'base_price' => 'required|numeric|min:0',
            'provider' => 'nullable|in:hotelbeds,ownerrez',
            'property_type' => 'nullable|string',
            'destination_code' => 'nullable|string',
            'check_in_date' => 'nullable|date',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        $result = $this->pricingService->testCalculation($request->all());
        
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
