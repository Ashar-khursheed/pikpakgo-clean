<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('business_name')->nullable();
            $table->string('business_registration_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('bio')->nullable();
            $table->json('languages_spoken')->nullable(); // ['en', 'es', 'fr']
            $table->integer('response_time_hours')->nullable(); // Average response time
            $table->decimal('response_rate', 5, 2)->default(0); // Percentage
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_document')->nullable();
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('is_verified');
            $table->index('verification_status');
            $table->index(['user_id', 'is_verified']); // Composite index
            $table->index(['is_verified', 'verification_status']); // Composite index
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_profiles');
    }
};
