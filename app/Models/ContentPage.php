<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'content',
        'type',
        'meta_title',
        'meta_description',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'content' => 'array', // Use JSON for flexible content if needed, or remove if simple text
    ];
}
