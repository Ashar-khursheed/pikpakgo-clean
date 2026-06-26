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
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('airline');
            $table->string('flight_number');
            $table->string('departure_airport_code', 3);
            $table->string('departure_airport_name');
            $table->string('arrival_airport_code', 3);
            $table->string('arrival_airport_name');
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->integer('stops')->default(0);
            $table->string('class')->default('Economy');
            $table->decimal('base_fare', 10, 2);
            $table->decimal('taxes', 10, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('departure_airport_code');
            $table->index('arrival_airport_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
