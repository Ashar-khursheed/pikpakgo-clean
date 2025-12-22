<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('agency_name');
            $table->string('agency_registration_number');
            $table->string('tax_id');
            $table->string('license_number')->nullable();
            $table->text('business_description')->nullable();
            $table->string('website')->nullable();
            $table->string('company_logo')->nullable();
            $table->integer('years_in_business')->nullable();
            $table->integer('number_of_employees')->nullable();
            
            // White-label settings
            $table->boolean('white_label_enabled')->default(false);
            $table->string('custom_domain')->nullable();
            $table->json('brand_colors')->nullable(); 
            $table->decimal('default_markup_percentage', 5, 2)->default(0); // Default markup for bookings
            
            // Billing & Commissions
            $table->enum('billing_type', ['commission', 'markup', 'hybrid'])->default('commission');
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->string('payment_terms')->nullable(); // e.g., "Net 30"
            $table->string('billing_contact_email')->nullable();
            $table->string('billing_contact_phone')->nullable();
            
            // Verification
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->json('verification_documents')->nullable();
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            
            // Status
        $table->enum('status', ['pending', 'active', 'inactive', 'suspended'])->default('pending');
            
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('is_verified');
            $table->index('white_label_enabled');
            $table->index('status');
            $table->index('verification_status');
            $table->index(['user_id', 'is_verified']); // Composite index
            $table->index(['white_label_enabled', 'status']); // Composite for B2B queries
            $table->index(['is_verified', 'status']); // Composite index
            $table->index('custom_domain'); // For white-label domain lookups
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_profiles');
    }
};
