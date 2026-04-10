<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyFee extends Model
{
    protected $fillable = [
        'property_listing_id',
        'fee_type',
        'fee_name',
        'amount',
        'amount_type',
        'applies_to',
        'is_mandatory',
        'is_taxable',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_mandatory' => 'boolean',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function propertyListing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate the fee amount given a base price, nights, and guests
     */
    public function calculate(float $basePrice, int $nights = 1, int $guests = 1): float
    {
        $amount = match ($this->applies_to) {
            'per_night' => $this->amount * $nights,
            'per_guest' => $this->amount * $guests,
            default => $this->amount, // per_stay
        };

        if ($this->amount_type === 'percentage') {
            $amount = $basePrice * ($this->amount / 100);
            if ($this->applies_to === 'per_night') {
                $amount *= $nights;
            } elseif ($this->applies_to === 'per_guest') {
                $amount *= $guests;
            }
        }

        return round($amount, 2);
    }
}
