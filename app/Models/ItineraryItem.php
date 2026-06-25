<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryItem extends Model
{
    protected $table = 'itinerary_items';

    protected $fillable = [
        'itinerary_id',
        'item_type',
        'item_id',
        'item_details',
        'price',
    ];

    protected $casts = [
        'item_details' => 'array',
        'price' => 'decimal:2',
    ];

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }
}
