<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $table = 'cars';

    protected $fillable = [
        'rental_company',
        'car_model',
        'car_class',
        'pickup_location',
        'dropoff_location',
        'transmission',
        'fuel_type',
        'mileage',
        'daily_rate',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
