<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardLedger extends Model
{
    protected $table = 'rewards_ledger';

    protected $fillable = [
        'user_id',
        'booking_id',
        'points',
        'type',
        'tier_applied',
        'description',
    ];

    /**
     * Get the user that owns this rewards record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the booking associated with this rewards record.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
