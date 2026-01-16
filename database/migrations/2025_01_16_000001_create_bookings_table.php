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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference')->unique(); // Our internal reference
            $table->string('provider_booking_reference')->nullable(); // Hotelbeds/OwnerRez reference
            $table->enum('provider', ['hotelbeds', 'ownerrez']); // Booking provider
            
            // User information (nullable for guest bookings)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('guest_email')->nullable(); // For guest bookings
            $table->string('guest_phone')->nullable();
            $table->string('guest_session_id')->nullable(); // Track anonymous users
            
            // Booking holder details
            $table->string('holder_first_name');
            $table->string('holder_last_name');
            $table->string('holder_email');
            $table->string('holder_phone');
            $table->string('holder_country_code')->nullable();
            
            // Property/Hotel details
            $table->string('property_code'); // Hotel code or property ID
            $table->string('property_name');
            $table->string('property_type')->default('hotel'); // hotel, vacation_rental, etc.
            $table->text('property_address')->nullable();
            $table->string('property_city')->nullable();
            $table->string('property_country')->nullable();
            $table->decimal('property_latitude', 10, 7)->nullable();
            $table->decimal('property_longitude', 10, 7)->nullable();
            
            // Booking dates
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('nights');
            
            // Room/Guest details
            $table->integer('total_rooms')->default(1);
            $table->integer('total_adults');
            $table->integer('total_children')->default(0);
            $table->json('room_details')->nullable(); // Array of room types and guests
            
            // Pricing
            $table->decimal('base_price', 10, 2); // Original API price
            $table->decimal('markup_amount', 10, 2)->default(0); // Our markup
            $table->decimal('markup_percentage', 5, 2)->default(0); // Markup percentage applied
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2); // Final price customer pays
            $table->string('currency', 3)->default('USD');
            
            // Payment details
            $table->enum('payment_status', [
                'pending',
                'processing',
                'paid',
                'failed',
                'refunded',
                'partially_refunded'
            ])->default('pending');
            $table->string('payment_method')->nullable(); // authorize.net, card, etc.
            $table->string('payment_transaction_id')->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            
            // Booking status
            $table->enum('booking_status', [
                'pending',
                'confirmed',
                'cancelled',
                'completed',
                'no_show',
                'rejected'
            ])->default('pending');
            
            // Cancellation details
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by')->nullable(); // user, admin, system
            $table->text('cancellation_reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->boolean('is_refundable')->default(true);
            $table->date('free_cancellation_until')->nullable();
            
            // Additional information
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable(); // Admin notes
            $table->json('metadata')->nullable(); // Additional data from APIs
            $table->json('api_response')->nullable(); // Full API response for reference
            
            // Confirmation
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_code')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('booking_reference');
            $table->index('provider_booking_reference');
            $table->index('user_id');
            $table->index('guest_email');
            $table->index('guest_session_id');
            $table->index('booking_status');
            $table->index('payment_status');
            $table->index('check_in_date');
            $table->index('check_out_date');
            $table->index('property_code');
            $table->index('created_at');
            $table->index(['user_id', 'booking_status']);
            $table->index(['guest_email', 'booking_status']);
            $table->index(['check_in_date', 'check_out_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
