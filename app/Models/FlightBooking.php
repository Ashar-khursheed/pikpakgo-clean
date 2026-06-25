<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightBooking extends Model
{
    protected $table = 'flight_bookings';

    protected $fillable = [
        'user_id',
        'booking_reference',
        'airline',
        'flight_number',
        'departure_airport',
        'arrival_airport',
        'departure_time',
        'arrival_time',
        'passenger_details',
        'total_price',
        'currency',
        'status',
    ];

    protected $casts = [
        'passenger_details' => 'array',
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
