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
        // Flight Bookings
        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('booking_reference')->unique();
            $table->string('airline');
            $table->string('flight_number');
            $table->string('departure_airport');
            $table->string('arrival_airport');
            $table->dateTime('departure_time');
            $table->dateTime('arrival_time');
            $table->json('passenger_details'); // details of all travelers
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, confirmed, cancelled
            $table->timestamps();

            $table->index('booking_reference');
            $table->index('user_id');
        });

        // Car Rental Bookings
        Schema::create('car_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('booking_reference')->unique();
            $table->string('rental_company');
            $table->string('car_model');
            $table->string('car_class');
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->dateTime('pickup_time');
            $table->dateTime('dropoff_time');
            $table->json('driver_details');
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('booking_reference');
            $table->index('user_id');
        });

        // Experience & Theme Park Bookings
        Schema::create('experience_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('booking_reference')->unique();
            $table->string('experience_name');
            $table->string('category'); // experience, theme_park
            $table->dateTime('activity_date');
            $table->integer('quantity');
            $table->json('ticket_details');
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('booking_reference');
            $table->index('user_id');
        });

        // Transfer Bookings
        Schema::create('transfer_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('booking_reference')->unique();
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->dateTime('transfer_time');
            $table->string('transfer_type'); // private, shared, shuttle
            $table->integer('passenger_count');
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('booking_reference');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_bookings');
        Schema::dropIfExists('experience_bookings');
        Schema::dropIfExists('car_bookings');
        Schema::dropIfExists('flight_bookings');
    }
};
