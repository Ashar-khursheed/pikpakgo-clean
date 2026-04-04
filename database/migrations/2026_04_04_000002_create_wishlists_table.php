<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('property_code');
            $table->string('property_name');
            $table->string('provider')->default('ownerrez');
            $table->string('property_city')->nullable();
            $table->string('property_country')->nullable();
            $table->string('featured_image')->nullable();
            $table->decimal('price_from', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->unique(['user_id', 'property_code']);
            $table->index('user_id');
            $table->index('property_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
