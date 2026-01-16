<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique(); // Our internal transaction ID
            
            // Booking relationship
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            
            // User relationship (nullable for guest payments)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('guest_session_id')->nullable();
            
            // Payment gateway details
            $table->string('payment_gateway')->default('authorize_net'); // authorize_net, stripe, paypal, etc.
            $table->string('gateway_transaction_id')->nullable(); // Gateway's transaction reference
            $table->string('gateway_response_code')->nullable();
            $table->text('gateway_response_message')->nullable();
            
            // Transaction details
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('transaction_type', [
                'payment',
                'refund',
                'partial_refund',
                'authorization',
                'capture',
                'void'
            ])->default('payment');
            
            // Payment method
            $table->enum('payment_method', [
                'credit_card',
                'debit_card',
                'paypal',
                'bank_transfer',
                'wallet'
            ])->nullable();
            
            // Card details (last 4 digits only for security)
            $table->string('card_brand')->nullable(); // Visa, Mastercard, Amex
            $table->string('card_last_four')->nullable();
            $table->string('card_expiry_month')->nullable();
            $table->string('card_expiry_year')->nullable();
            $table->string('card_holder_name')->nullable();
            
            // Billing address
            $table->string('billing_first_name')->nullable();
            $table->string('billing_last_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_postal_code')->nullable();
            
            // Transaction status
            $table->enum('status', [
                'pending',
                'processing',
                'success',
                'failed',
                'cancelled',
                'refunded',
                'partially_refunded',
                'authorized',
                'captured',
                'voided',
                'declined',
                'error'
            ])->default('pending');
            
            // Related transactions (for refunds, captures)
            $table->foreignId('parent_transaction_id')->nullable()->constrained('payment_transactions')->onDelete('set null');
            
            // Refund details
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();
            
            // Fraud detection
            $table->decimal('fraud_score', 5, 2)->nullable(); // 0-100 risk score
            $table->json('fraud_details')->nullable();
            $table->boolean('is_flagged')->default(false);
            
            // Additional data
            $table->json('metadata')->nullable(); // Additional transaction data
            $table->json('gateway_raw_response')->nullable(); // Full gateway response
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            
            // IP and device tracking
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            // Timestamps
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('transaction_id');
            $table->index('booking_id');
            $table->index('user_id');
            $table->index('guest_session_id');
            $table->index('payment_gateway');
            $table->index('gateway_transaction_id');
            $table->index('status');
            $table->index('transaction_type');
            $table->index('created_at');
            $table->index(['booking_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
