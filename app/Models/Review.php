<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'booking_reference', 'property_code', 'property_name',
        'provider', 'rating', 'title', 'body',
        'cleanliness_rating', 'accuracy_rating', 'communication_rating',
        'location_rating', 'value_rating',
        'status', 'admin_reply', 'admin_replied_at',
    ];

    protected $casts = [
        'admin_replied_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
