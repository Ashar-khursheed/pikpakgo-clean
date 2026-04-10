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
        Schema::create('property_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_listing_id');
            $table->enum('fee_type', ['cleaning_fee', 'service_fee', 'city_tax', 'damage_deposit', 'other']);
            $table->string('fee_name');
            $table->decimal('amount', 10, 2);
            $table->enum('amount_type', ['fixed', 'percentage'])->default('fixed');
            $table->enum('applies_to', ['per_stay', 'per_night', 'per_guest'])->default('per_stay');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('property_listing_id')->references('id')->on('property_listings')->onDelete('cascade');
            $table->index(['property_listing_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_fees');
    }
};
