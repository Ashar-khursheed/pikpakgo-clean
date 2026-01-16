<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'booking_id',
        'user_id',
        'guest_session_id',
        'payment_gateway',
        'gateway_transaction_id',
        'gateway_response_code',
        'gateway_response_message',
        'amount',
        'currency',
        'transaction_type',
        'payment_method',
        'card_brand',
        'card_last_four',
        'card_expiry_month',
        'card_expiry_year',
        'card_holder_name',
        'billing_first_name',
        'billing_last_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_country',
        'billing_postal_code',
        'status',
        'parent_transaction_id',
        'refund_amount',
        'refunded_at',
        'refund_reason',
        'fraud_score',
        'fraud_details',
        'is_flagged',
        'metadata',
        'gateway_raw_response',
        'customer_note',
        'internal_note',
        'ip_address',
        'user_agent',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'fraud_score' => 'decimal:2',
        'fraud_details' => 'array',
        'metadata' => 'array',
        'gateway_raw_response' => 'array',
        'is_flagged' => 'boolean',
        'refunded_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the booking associated with this transaction
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the user associated with this transaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the guest session associated with this transaction
     */
    public function guestSession()
    {
        return $this->belongsTo(GuestSession::class, 'guest_session_id', 'session_id');
    }

    /**
     * Get the parent transaction (for refunds)
     */
    public function parentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'parent_transaction_id');
    }

    /**
     * Get child transactions (refunds, captures)
     */
    public function childTransactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'parent_transaction_id');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for filtering by payment gateway
     */
    public function scopeGateway($query, $gateway)
    {
        return $query->where('payment_gateway', $gateway);
    }

    /**
     * Scope for filtering by transaction type
     */
    public function scopeType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Check if transaction is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if transaction is failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'declined', 'error']);
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Check if transaction can be refunded
     */
    public function canBeRefunded(): bool
    {
        if (!$this->isSuccessful()) {
            return false;
        }

        if ($this->transaction_type !== 'payment') {
            return false;
        }

        if ($this->refund_amount && $this->refund_amount >= $this->amount) {
            return false; // Already fully refunded
        }

        return true;
    }

    /**
     * Get refund status
     */
    public function getRefundStatusAttribute(): string
    {
        if (!$this->refund_amount) {
            return 'not_refunded';
        }

        if ($this->refund_amount >= $this->amount) {
            return 'fully_refunded';
        }

        return 'partially_refunded';
    }

    /**
     * Get masked card number
     */
    public function getMaskedCardNumberAttribute(): ?string
    {
        if (!$this->card_last_four) {
            return null;
        }

        return '**** **** **** ' . $this->card_last_four;
    }

    /**
     * Get transaction type label
     */
    public function getTransactionTypeLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->transaction_type));
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    /**
     * Get gateway label
     */
    public function getGatewayLabelAttribute(): string
    {
        return match($this->payment_gateway) {
            'authorize_net' => 'Authorize.Net',
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            default => ucfirst($this->payment_gateway)
        };
    }
}
