<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingMarkup;
use App\Services\PricingMarkupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PricingMarkupController extends Controller
{
    protected $pricingService;
    
    public function __construct(PricingMarkupService $pricingService)
    {
        $this->pricingService = $pricingService;
    }
    
    /**
     * Get all pricing markups
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
     * Store new pricing markup
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
     * Show specific markup
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
     * Update pricing markup
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
     * Delete pricing markup
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
     * Toggle markup status
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
     * Set as default markup
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
     * Calculate markup (test calculator)
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
