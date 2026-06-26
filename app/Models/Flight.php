<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    protected $table = 'flights';

    protected $fillable = [
        'airline',
        'flight_number',
        'departure_airport_code',
        'departure_airport_name',
        'arrival_airport_code',
        'arrival_airport_name',
        'departure_time',
        'arrival_time',
        'stops',
        'class',
        'base_fare',
        'taxes',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'stops' => 'integer',
        'base_fare' => 'decimal:2',
        'taxes' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
