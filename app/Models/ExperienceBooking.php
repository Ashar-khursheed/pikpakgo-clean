<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceBooking extends Model
{
    protected $table = 'experience_bookings';

    protected $fillable = [
        'user_id',
        'booking_reference',
        'experience_name',
        'category',
        'activity_date',
        'quantity',
        'ticket_details',
        'total_price',
        'currency',
        'status',
    ];

    protected $casts = [
        'ticket_details' => 'array',
        'activity_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
