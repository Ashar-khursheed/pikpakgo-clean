<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgencyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agency_name',
        'agency_registration_number',
        'tax_id',
        'license_number',
        'business_description',
        'website',
        'company_logo',
        'years_in_business',
        'number_of_employees',
        'white_label_enabled',
        'custom_domain',
        'brand_colors',
        'default_markup_percentage',
        'billing_type',
        'commission_rate',
        'payment_terms',
        'billing_contact_email',
        'billing_contact_phone',
        'is_verified',
        'verified_at',
        'verification_documents',
        'verification_status',
        'rejection_reason',
        'status',
    ];

    protected $casts = [
        'brand_colors' => 'array',
        'verification_documents' => 'array',
        'is_verified' => 'boolean',
        'white_label_enabled' => 'boolean',
        'verified_at' => 'datetime',
        'default_markup_percentage' => 'decimal:2',
        'commission_rate' => 'decimal:2',
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWhiteLabel($query)
    {
        return $query->where('white_label_enabled', true);
    }
}
