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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('rental_company');
            $table->string('car_model');
            $table->string('car_class');
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->string('transmission')->default('Automatic');
            $table->string('fuel_type')->default('Gasoline');
            $table->string('mileage')->default('Unlimited');
            $table->decimal('daily_rate', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('pickup_location');
            $table->index('dropoff_location');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
