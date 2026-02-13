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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            
            // Location
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            
            // Details
            $table->decimal('price_per_night', 10, 2);
            $table->integer('bedrooms')->default(1);
            $table->integer('bathrooms')->default(1);
            $table->integer('guests')->default(2);
            $table->string('property_type')->default('apartment'); // apartment, house, hotel
            
            // Content
            $table->json('images')->nullable();
            $table->json('amenities')->nullable();
            $table->json('rules')->nullable();
            $table->json('policies')->nullable();
            
            // Sync / External
            $table->string('external_id')->nullable()->index(); // ID from OwnerRez or Hotelbeds
            $table->string('source')->default('local'); // local, hotelbeds, ownerrez
            $table->json('raw_data')->nullable(); // Store original API response
            
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
