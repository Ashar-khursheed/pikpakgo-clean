<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

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

    protected $appends = [
        'full_name',
        'role_id',
        'role_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_token',
        'reset_token',
        'roles', // Hide the roles collection to keep response clean
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'reset_token_expires_at' => 'datetime',
        'date_of_birth' => 'date',
    ];

    protected static function booted()
    {
        static::created(function ($user) {
            $user->assignRoleByUserType();
        });

        static::updated(function ($user) {
            if ($user->isDirty('user_type')) {
                $user->assignRoleByUserType();
            }
        });
    }

    /**
     * Assign role based on user_type
     */
    public function assignRoleByUserType()
    {
        $roleName = $this->user_type;
        
        // Handle special cases if any
        if ($this->user_type === 'admin') {
            // Check if super-admin role exists and if this user should have it
            // For now, default to 'admin' role
            $roleName = 'admin';
        }

        // Use firstOrCreate style logic or just sync
        // Spatie's syncRoles will handle it
        try {
            $this->syncRoles([$roleName]);
        } catch (\Exception $e) {
            // Role might not exist in DB yet
            \Log::warning("Could not assign role {$roleName} to user {$this->id}: " . $e->getMessage());
        }
    }

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
    public function bookings()
    {
        return $this->hasMany(\App\Models\Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(\App\Models\Review::class);
    }

    public function wishlist()
    {
        return $this->hasMany(\App\Models\Wishlist::class);
    }

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

    public function getRoleIdAttribute()
    {
        return $this->roles->first()?->id;
    }

    public function getRoleNameAttribute()
    {
        return $this->roles->first()?->name;
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
