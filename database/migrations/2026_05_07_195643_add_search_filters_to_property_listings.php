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
        Schema::table('property_listings', function (Blueprint $table) {
            $table->integer('bedrooms')->nullable()->after('total_rooms');
            $table->integer('bathrooms')->nullable()->after('bedrooms');
            $table->integer('max_guests')->nullable()->after('bathrooms');
            $table->boolean('instant_book')->default(false)->after('is_featured');
            
            // Add indexes for search performance
            $table->index('bedrooms');
            $table->index('bathrooms');
            $table->index('max_guests');
            $table->index('instant_book');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            $table->dropIndex(['bedrooms']);
            $table->dropIndex(['bathrooms']);
            $table->dropIndex(['max_guests']);
            $table->dropIndex(['instant_book']);
            
            $table->dropColumn(['bedrooms', 'bathrooms', 'max_guests', 'instant_book']);
        });
    }
};
