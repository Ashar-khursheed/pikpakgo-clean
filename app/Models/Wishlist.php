<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    protected $fillable = [
        'user_id', 'property_code', 'property_name', 'provider',
        'property_city', 'property_country', 'featured_image',
        'price_from', 'currency',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
