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
        Schema::create('pricing_markups', function (Blueprint $table) {
            $table->id();
            
            // Markup configuration
            $table->string('name'); // e.g., "Default Hotels", "Luxury Hotels", "Vacation Rentals"
            $table->text('description')->nullable();
            
            // Markup type
            $table->enum('markup_type', ['percentage', 'fixed', 'tiered'])->default('percentage');
            $table->decimal('markup_percentage', 5, 2)->nullable(); // e.g., 15.00 for 15%
            $table->decimal('markup_fixed_amount', 10, 2)->nullable(); // e.g., 50.00
            
            // Tiered pricing (JSON structure for different price ranges)
            // Example: [{"min": 0, "max": 100, "percentage": 20}, {"min": 100, "max": 500, "percentage": 15}]
            $table->json('tiered_pricing')->nullable();
            
            // Applicability
            $table->enum('provider', ['hotelbeds', 'ownerrez', 'all'])->default('all');
            $table->string('property_type')->nullable(); // hotel, apartment, villa, etc.
            $table->string('destination_code')->nullable(); // Specific destination
            $table->decimal('min_price', 10, 2)->nullable(); // Minimum booking price to apply
            $table->decimal('max_price', 10, 2)->nullable(); // Maximum booking price to apply
            
            // Date range (seasonal pricing)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            
            // Priority and status
            $table->integer('priority')->default(0); // Higher priority rules apply first
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default markup if no rules match
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('provider');
            $table->index('property_type');
            $table->index('is_active');
            $table->index('is_default');
            $table->index('priority');
            $table->index(['valid_from', 'valid_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_markups');
    }
};
