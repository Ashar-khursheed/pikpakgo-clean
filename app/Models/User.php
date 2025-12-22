<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_country_code',
        'password',
        'user_type',
        'status',
        'profile_image',
        'date_of_birth',
        'gender',
        'country',
        'city',
        'state',
        'zip_code',
        'address',
        'preferred_currency',
        'preferred_language',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_token',
        'reset_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'reset_token_expires_at' => 'datetime',
        'date_of_birth' => 'date',
    ];

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

//  public function getJWTCustomClaims()
// {
//     return [
//         'email' => $this->email,
//         'user_type' => $this->user_type,
//     ];
// }
public function getJWTCustomClaims()
{
    return [];
}
    // Relationships
    public function hostProfile()
    {
        return $this->hasOne(HostProfile::class);
    }

    public function agencyProfile()
    {
        return $this->hasOne(AgencyProfile::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('user_type', $type);
    }

    // Helper Methods
    public function isCustomer()
    {
        return $this->user_type === 'customer';
    }

    public function isHost()
    {
        return $this->user_type === 'host';
    }

    public function isAgency()
    {
        return $this->user_type === 'agency';
    }

    public function isAdmin()
    {
        return $this->user_type === 'admin';
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isVerified()
    {
        return !is_null($this->email_verified_at);
    }
}
