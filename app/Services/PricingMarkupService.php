<?php

namespace App\Services;

use App\Models\PricingMarkup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PricingMarkupService
{
    /**
     * Calculate markup for a given booking
     *
     * @param array $bookingData
     * @return array
     */
    public function calculateMarkup(array $bookingData): array
    {
        $basePrice = $bookingData['base_price'] ?? 0;
        
        if ($basePrice <= 0) {
            return [
                'base_price' => 0,
                'markup_amount' => 0,
                'markup_percentage' => 0,
                'final_price' => 0,
                'applied_rule' => null
            ];
        }
        
        // Find applicable markup rule
        $markupRule = $this->findApplicableMarkup($bookingData);
        
        if (!$markupRule) {
            return [
                'base_price' => $basePrice,
                'markup_amount' => 0,
                'markup_percentage' => 0,
                'final_price' => $basePrice,
                'applied_rule' => null
            ];
        }
        
        // Calculate markup based on rule type
        $markupAmount = 0;
        $markupPercentage = 0;
        
        switch ($markupRule->markup_type) {
            case 'percentage':
                $markupPercentage = $markupRule->markup_percentage ?? 0;
                $markupAmount = ($basePrice * $markupPercentage) / 100;
                break;
                
            case 'fixed':
                $markupAmount = $markupRule->markup_fixed_amount ?? 0;
                $markupPercentage = ($markupAmount / $basePrice) * 100;
                break;
                
            case 'tiered':
                $tieredResult = $this->calculateTieredMarkup($basePrice, $markupRule->tiered_pricing);
                $markupAmount = $tieredResult['markup_amount'];
                $markupPercentage = $tieredResult['markup_percentage'];
                break;
        }
        
        $finalPrice = $basePrice + $markupAmount;
        
        return [
            'base_price' => round($basePrice, 2),
            'markup_amount' => round($markupAmount, 2),
            'markup_percentage' => round($markupPercentage, 2),
            'final_price' => round($finalPrice, 2),
            'applied_rule' => [
                'id' => $markupRule->id,
                'name' => $markupRule->name,
                'type' => $markupRule->markup_type
            ]
        ];
    }
    
    /**
     * Find the most applicable markup rule
     *
     * @param array $bookingData
     * @return PricingMarkup|null
     */
    protected function findApplicableMarkup(array $bookingData): ?PricingMarkup
    {
        $cacheKey = 'markup_rules';
        
        // Get all active markup rules (cached for 30 minutes)
        $rules = Cache::remember($cacheKey, 1800, function () {
            return PricingMarkup::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->get();
        });
        
        $provider = $bookingData['provider'] ?? null;
        $propertyType = $bookingData['property_type'] ?? null;
        $destinationCode = $bookingData['destination_code'] ?? null;
        $basePrice = $bookingData['base_price'] ?? 0;
        $checkInDate = $bookingData['check_in_date'] ?? now()->toDateString();
        
        foreach ($rules as $rule) {
            // Check provider match
            if ($rule->provider !== 'all' && $rule->provider !== $provider) {
                continue;
            }
            
            // Check property type match
            if ($rule->property_type && $rule->property_type !== $propertyType) {
                continue;
            }
            
            // Check destination match
            if ($rule->destination_code && $rule->destination_code !== $destinationCode) {
                continue;
            }
            
            // Check price range
            if ($rule->min_price && $basePrice < $rule->min_price) {
                continue;
            }
            
            if ($rule->max_price && $basePrice > $rule->max_price) {
                continue;
            }
            
            // Check date range
            if ($rule->valid_from && $checkInDate < $rule->valid_from) {
                continue;
            }
            
            if ($rule->valid_to && $checkInDate > $rule->valid_to) {
                continue;
            }
            
            // If all conditions match, return this rule
            return $rule;
        }
        
        // If no specific rule matches, return default rule
        return $rules->where('is_default', true)->first();
    }
    
    /**
     * Calculate tiered pricing markup
     *
     * @param float $basePrice
     * @param array|null $tieredPricing
     * @return array
     */
    protected function calculateTieredMarkup(float $basePrice, ?array $tieredPricing): array
    {
        if (!$tieredPricing) {
            return ['markup_amount' => 0, 'markup_percentage' => 0];
        }
        
        foreach ($tieredPricing as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_FLOAT_MAX;
            
            if ($basePrice >= $min && $basePrice <= $max) {
                $percentage = $tier['percentage'] ?? 0;
                $markupAmount = ($basePrice * $percentage) / 100;
                
                return [
                    'markup_amount' => $markupAmount,
                    'markup_percentage' => $percentage
                ];
            }
        }
        
        return ['markup_amount' => 0, 'markup_percentage' => 0];
    }
    
    /**
     * Clear markup rules cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget('markup_rules');
    }
    
    /**
     * Get all active markup rules
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveRules()
    {
        return PricingMarkup::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }
    
    /**
     * Test markup calculation
     *
     * @param array $testData
     * @return array
     */
    public function testCalculation(array $testData): array
    {
        $result = $this->calculateMarkup($testData);
        
        return [
            'test_input' => $testData,
            'calculation_result' => $result,
            'breakdown' => [
                'base_price' => $result['base_price'],
                'markup_percentage' => $result['markup_percentage'] . '%',
                'markup_amount' => '$' . number_format($result['markup_amount'], 2),
                'final_price' => '$' . number_format($result['final_price'], 2),
                'applied_rule' => $result['applied_rule']
            ]
        ];
    }
}
