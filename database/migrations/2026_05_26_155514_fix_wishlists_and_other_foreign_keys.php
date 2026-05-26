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
        // 1. Fix wishlists table
        try {
            Schema::table('wishlists', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Exception $e) {
            // Ignore if foreign key doesn't exist or is named differently
        }

        try {
            Schema::table('wishlists', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            // Ignore if already exists or fails
        }

        // 2. Fix reviews table
        try {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            // Ignore
        }

        // 3. Fix user_notifications table
        try {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            // Ignore
        }

        // 4. Fix host_profiles table
        try {
            Schema::table('host_profiles', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('host_profiles', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            // Ignore
        }

        // 5. Fix bookings table
        try {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            // Ignore
        }

        // 6. Fix payment_transactions table
        try {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Ignore
        }

        // 7. Fix guest_sessions table
        try {
            Schema::table('guest_sessions', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('guest_sessions', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - the fix is the correct database state
    }
};
