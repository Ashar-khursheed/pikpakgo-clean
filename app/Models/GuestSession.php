<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuestSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'session_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'country',
        'ip_address',
        'user_agent',
        'device_info',
        'search_count',
        'booking_count',
        'last_activity_at',
        'first_activity_at',
        'converted_to_user',
        'user_id',
        'converted_at',
        'expires_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'converted_to_user' => 'boolean',
        'last_activity_at' => 'datetime',
        'first_activity_at' => 'datetime',
        'converted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user this session was converted to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get bookings for this guest session
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'guest_session_id', 'session_id');
    }

    /**
     * Scope for active sessions (not expired)
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope for expired sessions
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope for converted sessions
     */
    public function scopeConverted($query)
    {
        return $query->where('converted_to_user', true);
    }

    /**
     * Scope for sessions with bookings
     */
    public function scopeWithBookings($query)
    {
        return $query->where('booking_count', '>', 0);
    }

    /**
     * Update activity timestamp
     */
    public function updateActivity(): void
    {
        $this->update([
            'last_activity_at' => now()
        ]);

        if (!$this->first_activity_at) {
            $this->update([
                'first_activity_at' => now()
            ]);
        }
    }

    /**
     * Increment search count
     */
    public function incrementSearchCount(): void
    {
        $this->increment('search_count');
        $this->updateActivity();
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->gte($this->expires_at);
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Extend session expiry
     */
    public function extendExpiry(int $days = 30): void
    {
        $this->update([
            'expires_at' => now()->addDays($days)
        ]);
    }

    /**
     * Convert to registered user
     */
    public function convertToUser(User $user): void
    {
        $this->update([
            'converted_to_user' => true,
            'user_id' => $user->id,
            'converted_at' => now()
        ]);

        // Update all guest bookings to be associated with the user
        $this->bookings()->update([
            'user_id' => $user->id
        ]);
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): ?string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }

        return $this->first_name ?? $this->last_name ?? null;
    }

    /**
     * Get conversion rate (bookings / searches)
     */
    public function getConversionRateAttribute(): float
    {
        if ($this->search_count === 0) {
            return 0;
        }

        return ($this->booking_count / $this->search_count) * 100;
    }
}
