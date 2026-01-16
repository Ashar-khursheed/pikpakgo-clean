<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingMarkup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'markup_type',
        'markup_percentage',
        'markup_fixed_amount',
        'tiered_pricing',
        'provider',
        'property_type',
        'destination_code',
        'min_price',
        'max_price',
        'valid_from',
        'valid_to',
        'priority',
        'is_active',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'markup_percentage' => 'decimal:2',
        'markup_fixed_amount' => 'decimal:2',
        'tiered_pricing' => 'array',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the user who created this markup
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this markup
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for active markups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default markup
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for filtering by provider
     */
    public function scopeProvider($query, $provider)
    {
        return $query->where(function($q) use ($provider) {
            $q->where('provider', $provider)
              ->orWhere('provider', 'all');
        });
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeValidOn($query, $date)
    {
        return $query->where(function($q) use ($date) {
            $q->where(function($subQ) use ($date) {
                $subQ->where('valid_from', '<=', $date)
                     ->orWhereNull('valid_from');
            })
            ->where(function($subQ) use ($date) {
                $subQ->where('valid_to', '>=', $date)
                     ->orWhereNull('valid_to');
            });
        });
    }

    /**
     * Check if markup is currently valid
     */
    public function isValidNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now()->toDateString();

        if ($this->valid_from && $now < $this->valid_from) {
            return false;
        }

        if ($this->valid_to && $now > $this->valid_to) {
            return false;
        }

        return true;
    }

    /**
     * Calculate markup for a given price
     */
    public function calculateForPrice(float $price): array
    {
        $markupAmount = 0;
        $markupPercentage = 0;

        switch ($this->markup_type) {
            case 'percentage':
                $markupPercentage = $this->markup_percentage ?? 0;
                $markupAmount = ($price * $markupPercentage) / 100;
                break;

            case 'fixed':
                $markupAmount = $this->markup_fixed_amount ?? 0;
                $markupPercentage = ($markupAmount / $price) * 100;
                break;

            case 'tiered':
                $tier = $this->findTierForPrice($price);
                if ($tier) {
                    $markupPercentage = $tier['percentage'] ?? 0;
                    $markupAmount = ($price * $markupPercentage) / 100;
                }
                break;
        }

        return [
            'markup_amount' => round($markupAmount, 2),
            'markup_percentage' => round($markupPercentage, 2),
            'final_price' => round($price + $markupAmount, 2),
        ];
    }

    /**
     * Find the appropriate tier for a given price
     */
    protected function findTierForPrice(float $price): ?array
    {
        if (!$this->tiered_pricing) {
            return null;
        }

        foreach ($this->tiered_pricing as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_FLOAT_MAX;

            if ($price >= $min && $price <= $max) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Get markup type label
     */
    public function getMarkupTypeLabelAttribute(): string
    {
        return match($this->markup_type) {
            'percentage' => 'Percentage',
            'fixed' => 'Fixed Amount',
            'tiered' => 'Tiered Pricing',
            default => 'Unknown'
        };
    }

    /**
     * Get provider label
     */
    public function getProviderLabelAttribute(): string
    {
        return match($this->provider) {
            'hotelbeds' => 'Hotelbeds',
            'ownerrez' => 'OwnerRez',
            'all' => 'All Providers',
            default => 'Unknown'
        };
    }
}
