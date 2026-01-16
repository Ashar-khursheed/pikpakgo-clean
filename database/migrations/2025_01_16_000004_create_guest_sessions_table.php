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
        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique(); // Unique guest identifier
            
            // Guest information (collected during booking)
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            
            // Session tracking
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('device_info')->nullable();
            
            // Activity tracking
            $table->integer('search_count')->default(0);
            $table->integer('booking_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('first_activity_at')->nullable();
            
            // Conversion tracking
            $table->boolean('converted_to_user')->default(false); // If they register
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('converted_at')->nullable();
            
            // Session expiry
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('session_id');
            $table->index('email');
            $table->index('ip_address');
            $table->index('converted_to_user');
            $table->index('expires_at');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
    }
};
