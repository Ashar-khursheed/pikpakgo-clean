<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_registration_number',
        'tax_id',
        'bio',
        'languages_spoken',
        'response_time_hours',
        'response_rate',
        'is_verified',
        'verified_at',
        'verification_document',
        'verification_status',
        'rejection_reason',
    ];

    protected $casts = [
        'languages_spoken' => 'array',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'response_rate' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }
}
