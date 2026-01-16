<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_reference',
        'provider_booking_reference',
        'provider',
        'user_id',
        'guest_email',
        'guest_phone',
        'guest_session_id',
        'holder_first_name',
        'holder_last_name',
        'holder_email',
        'holder_phone',
        'holder_country_code',
        'property_code',
        'property_name',
        'property_type',
        'property_address',
        'property_city',
        'property_country',
        'property_latitude',
        'property_longitude',
        'check_in_date',
        'check_out_date',
        'nights',
        'total_rooms',
        'total_adults',
        'total_children',
        'room_details',
        'base_price',
        'markup_amount',
        'markup_percentage',
        'tax_amount',
        'total_price',
        'currency',
        'payment_status',
        'payment_method',
        'payment_transaction_id',
        'paid_amount',
        'paid_at',
        'booking_status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'refund_amount',
        'is_refundable',
        'free_cancellation_until',
        'special_requests',
        'internal_notes',
        'metadata',
        'api_response',
        'confirmed_at',
        'confirmation_code',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'room_details' => 'array',
        'metadata' => 'array',
        'api_response' => 'array',
        'base_price' => 'decimal:2',
        'markup_amount' => 'decimal:2',
        'markup_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'is_refundable' => 'boolean',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'free_cancellation_until' => 'date',
    ];

    /**
     * Get the user that owns the booking
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the guest session associated with the booking
     */
    public function guestSession()
    {
        return $this->belongsTo(GuestSession::class, 'guest_session_id', 'session_id');
    }

    /**
     * Get all payment transactions for this booking
     */
    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Get the property listing associated with this booking
     */
    public function propertyListing()
    {
        return $this->belongsTo(PropertyListing::class, 'property_code', 'provider_property_id');
    }

    /**
     * Scope for filtering by booking status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('booking_status', $status);
    }

    /**
     * Scope for filtering by payment status
     */
    public function scopePaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Scope for filtering by provider
     */
    public function scopeProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope for upcoming bookings
     */
    public function scopeUpcoming($query)
    {
        return $query->where('check_in_date', '>=', now()->toDateString())
            ->where('booking_status', '!=', 'cancelled');
    }

    /**
     * Scope for past bookings
     */
    public function scopePast($query)
    {
        return $query->where('check_out_date', '<', now()->toDateString());
    }

    /**
     * Scope for current bookings (checked in)
     */
    public function scopeCurrent($query)
    {
        return $query->where('check_in_date', '<=', now()->toDateString())
            ->where('check_out_date', '>=', now()->toDateString())
            ->where('booking_status', 'confirmed');
    }

    /**
     * Check if booking is cancellable
     */
    public function isCancellable(): bool
    {
        if ($this->booking_status === 'cancelled') {
            return false;
        }

        if ($this->free_cancellation_until) {
            return now()->lte($this->free_cancellation_until);
        }

        // Default: can cancel up to 24 hours before check-in
        return now()->lt($this->check_in_date->subDay());
    }

    /**
     * Check if booking is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->booking_status === 'confirmed';
    }

    /**
     * Check if booking is paid
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Get full holder name
     */
    public function getHolderFullNameAttribute(): string
    {
        return "{$this->holder_first_name} {$this->holder_last_name}";
    }

    /**
     * Get total guests count
     */
    public function getTotalGuestsAttribute(): int
    {
        return $this->total_adults + $this->total_children;
    }

    /**
     * Get booking type (guest or user)
     */
    public function getBookingTypeAttribute(): string
    {
        return $this->user_id ? 'user' : 'guest';
    }

    /**
     * Check if free cancellation is available
     */
    public function hasFreeCancellation(): bool
    {
        if (!$this->free_cancellation_until) {
            return false;
        }

        return now()->lte($this->free_cancellation_until);
    }

    /**
     * Calculate cancellation fee
     */
    public function calculateCancellationFee(): float
    {
        if ($this->hasFreeCancellation()) {
            return 0;
        }

        $daysUntilCheckIn = now()->diffInDays($this->check_in_date, false);

        // Cancellation policy (can be customized)
        if ($daysUntilCheckIn >= 7) {
            return 0; // No fee if cancelled 7+ days before
        } elseif ($daysUntilCheckIn >= 3) {
            return $this->total_price * 0.25; // 25% fee
        } elseif ($daysUntilCheckIn >= 1) {
            return $this->total_price * 0.50; // 50% fee
        } else {
            return $this->total_price; // Full charge for same-day cancellation
        }
    }
}
