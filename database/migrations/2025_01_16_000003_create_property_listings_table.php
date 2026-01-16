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
        Schema::create('property_listings', function (Blueprint $table) {
            $table->id();
            
            // Provider information
            $table->enum('provider', ['hotelbeds', 'ownerrez']);
            $table->string('provider_property_id'); // External API property ID
            $table->string('provider_code')->nullable(); // Hotel code or property code
            
            // Basic information
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('property_type')->default('hotel'); // hotel, apartment, villa, etc.
            $table->enum('category', ['budget', 'standard', 'superior', 'luxury'])->nullable();
            $table->integer('star_rating')->nullable(); // 1-5 stars
            
            // Location
            $table->string('country');
            $table->string('country_code', 3)->nullable();
            $table->string('state')->nullable();
            $table->string('city');
            $table->string('destination_code')->nullable(); // For search purposes
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            
            // Contact information
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            
            // Images
            $table->json('images')->nullable(); // Array of image URLs
            $table->string('featured_image')->nullable(); // Main image URL
            
            // Amenities and features
            $table->json('amenities')->nullable(); // Array of amenity codes/names
            $table->json('room_types')->nullable(); // Available room types
            $table->integer('total_rooms')->nullable();
            
            // Pricing information (cached)
            $table->decimal('price_from', 10, 2)->nullable(); // Starting price
            $table->string('price_currency', 3)->default('USD');
            $table->timestamp('price_last_updated')->nullable();
            
            // Policies
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->text('child_policy')->nullable();
            $table->text('pet_policy')->nullable();
            
            // Reviews and ratings
            $table->decimal('rating_average', 3, 2)->nullable(); // 0.00 to 5.00
            $table->integer('rating_count')->default(0);
            $table->json('rating_breakdown')->nullable(); // Cleanliness, Service, Location, etc.
            
            // Status and visibility
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('booking_count')->default(0);
            
            // API sync
            $table->json('api_data')->nullable(); // Full API response cached
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for search and performance
            $table->index('provider');
            $table->index('provider_property_id');
            $table->index('provider_code');
            $table->index('property_type');
            $table->index('city');
            $table->index('country_code');
            $table->index('destination_code');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('star_rating');
            $table->index('rating_average');
            $table->index('price_from');
            $table->index(['latitude', 'longitude']); // For geo searches
            $table->index(['provider', 'provider_property_id']); // Composite unique
            $table->index(['city', 'is_active']);
            $table->index(['destination_code', 'is_active']);
            $table->fullText(['name', 'description', 'city']); // Full-text search
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_listings');
    }
};
