<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarBooking extends Model
{
    protected $table = 'car_bookings';

    protected $fillable = [
        'user_id',
        'booking_reference',
        'rental_company',
        'car_model',
        'car_class',
        'pickup_location',
        'dropoff_location',
        'pickup_time',
        'dropoff_time',
        'driver_details',
        'total_price',
        'currency',
        'status',
    ];

    protected $casts = [
        'driver_details' => 'array',
        'pickup_time' => 'datetime',
        'dropoff_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
