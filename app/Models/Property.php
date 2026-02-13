<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'price_per_night',
        'bedrooms',
        'bathrooms',
        'guests',
        'property_type',
        'images',
        'amenities',
        'rules',
        'policies',
        'external_id',
        'source',
        'raw_data',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'images' => 'array',
        'amenities' => 'array',
        'rules' => 'array',
        'policies' => 'array',
        'raw_data' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'price_per_night' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];
}
