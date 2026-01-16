<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyListing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider',
        'provider_property_id',
        'provider_code',
        'name',
        'description',
        'property_type',
        'category',
        'star_rating',
        'country',
        'country_code',
        'state',
        'city',
        'destination_code',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'images',
        'featured_image',
        'amenities',
        'room_types',
        'total_rooms',
        'price_from',
        'price_currency',
        'price_last_updated',
        'check_in_time',
        'check_out_time',
        'cancellation_policy',
        'child_policy',
        'pet_policy',
        'rating_average',
        'rating_count',
        'rating_breakdown',
        'is_active',
        'is_featured',
        'view_count',
        'booking_count',
        'api_data',
        'last_synced_at',
        'next_sync_at',
    ];

    protected $casts = [
        'images' => 'array',
        'amenities' => 'array',
        'room_types' => 'array',
        'rating_breakdown' => 'array',
        'api_data' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'price_from' => 'decimal:2',
        'rating_average' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'last_synced_at' => 'datetime',
        'next_sync_at' => 'datetime',
        'price_last_updated' => 'datetime',
        'check_in_time' => 'datetime:H:i',
        'check_out_time' => 'datetime:H:i',
    ];

    /**
     * Get bookings for this property
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'property_code', 'provider_property_id');
    }

    /**
     * Scope for active properties
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured properties
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for filtering by provider
     */
    public function scopeProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope for filtering by city
     */
    public function scopeCity($query, $city)
    {
        return $query->where('city', 'LIKE', "%{$city}%");
    }

    /**
     * Scope for filtering by destination code
     */
    public function scopeDestination($query, $destinationCode)
    {
        return $query->where('destination_code', $destinationCode);
    }

    /**
     * Scope for filtering by price range
     */
    public function scopePriceRange($query, $min = null, $max = null)
    {
        if ($min !== null) {
            $query->where('price_from', '>=', $min);
        }

        if ($max !== null) {
            $query->where('price_from', '<=', $max);
        }

        return $query;
    }

    /**
     * Scope for filtering by star rating
     */
    public function scopeStarRating($query, $ratings)
    {
        if (is_array($ratings)) {
            return $query->whereIn('star_rating', $ratings);
        }

        return $query->where('star_rating', $ratings);
    }

    /**
     * Scope for searching by name or description
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%")
              ->orWhere('city', 'LIKE', "%{$search}%")
              ->orWhere('address', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Scope for geographic search (within radius)
     */
    public function scopeNearby($query, $lat, $lng, $radiusInKm = 10)
    {
        // Haversine formula for distance calculation
        $haversine = "(6371 * acos(cos(radians({$lat})) 
            * cos(radians(latitude)) 
            * cos(radians(longitude) - radians({$lng})) 
            + sin(radians({$lat})) 
            * sin(radians(latitude))))";

        return $query->selectRaw("*, {$haversine} AS distance")
            ->whereRaw("{$haversine} < ?", [$radiusInKm])
            ->orderBy('distance');
    }

    /**
     * Scope for properties needing sync
     */
    public function scopeNeedsSync($query)
    {
        return $query->where(function($q) {
            $q->whereNull('next_sync_at')
              ->orWhere('next_sync_at', '<=', now());
        });
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Increment booking count
     */
    public function incrementBookingCount(): void
    {
        $this->increment('booking_count');
    }

    /**
     * Check if property needs to be synced
     */
    public function needsSync(): bool
    {
        if (!$this->last_synced_at) {
            return true;
        }

        if ($this->next_sync_at && now()->gte($this->next_sync_at)) {
            return true;
        }

        // Sync if last sync was more than 24 hours ago
        return $this->last_synced_at->lt(now()->subHours(24));
    }

    /**
     * Mark as synced
     */
    public function markAsSynced(): void
    {
        $this->update([
            'last_synced_at' => now(),
            'next_sync_at' => now()->addHours(12) // Next sync in 12 hours
        ]);
    }

    /**
     * Get provider label
     */
    public function getProviderLabelAttribute(): string
    {
        return match($this->provider) {
            'hotelbeds' => 'Hotelbeds',
            'ownerrez' => 'OwnerRez',
            default => 'Unknown'
        };
    }

    /**
     * Get property type label
     */
    public function getPropertyTypeLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->property_type));
    }

    /**
     * Get formatted star rating
     */
    public function getStarRatingDisplayAttribute(): string
    {
        return $this->star_rating ? str_repeat('★', $this->star_rating) : 'N/A';
    }
}
