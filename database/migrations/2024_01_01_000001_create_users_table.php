<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('phone_country_code')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('user_type', ['customer', 'host', 'agency', 'admin'])->default('customer');
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])->default('pending');
            $table->string('profile_image')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->text('address')->nullable();
            $table->string('preferred_currency', 3)->default('USD');
            $table->string('preferred_language', 5)->default('en');
            $table->string('verification_token')->nullable();
            $table->string('reset_token')->nullable();
            $table->timestamp('reset_token_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('email');
            $table->index('user_type');
            $table->index('status');
            $table->index('email_verified_at');
            $table->index('created_at');
            $table->index(['user_type', 'status']); // Composite index for common queries
            $table->index(['email', 'status']); // Composite index for login queries
            $table->index('verification_token');
            $table->index('reset_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
