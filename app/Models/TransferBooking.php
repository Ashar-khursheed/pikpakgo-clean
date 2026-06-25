<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferBooking extends Model
{
    protected $table = 'transfer_bookings';

    protected $fillable = [
        'user_id',
        'booking_reference',
        'pickup_location',
        'dropoff_location',
        'transfer_time',
        'transfer_type',
        'passenger_count',
        'total_price',
        'currency',
        'status',
    ];

    protected $casts = [
        'transfer_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
