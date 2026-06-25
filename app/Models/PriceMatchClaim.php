<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceMatchClaim extends Model
{
    protected $table = 'price_match_claims';

    protected $fillable = [
        'user_id',
        'booking_reference',
        'competitor_url',
        'competitor_price',
        'screenshot_path',
        'status',
        'verification_notes',
        'refund_amount',
    ];

    /**
     * Get the user who submitted the claim.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the booking associated with the reference.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_reference', 'booking_reference');
    }
}
