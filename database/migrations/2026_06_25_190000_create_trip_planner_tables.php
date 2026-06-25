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
        Schema::create('itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('destination');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('interests')->nullable();
            $table->json('ai_recommendations')->nullable(); // Saved LLM output
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained()->onDelete('cascade');
            $table->string('item_type'); // hotel, flight, car, experience, transfer
            $table->string('item_id'); // ID of the referenced listing/flight
            $table->json('item_details'); // Details of the selected item
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->index('itinerary_id');
            $table->index('item_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_items');
        Schema::dropIfExists('itineraries');
    }
};
