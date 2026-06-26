<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    protected $table = 'experiences';

    protected $fillable = [
        'name',
        'category',
        'location',
        'duration',
        'rating',
        'price_per_ticket',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'price_per_ticket' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
