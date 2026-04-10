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
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->after('user_id');
            $table->boolean('is_verified')->default(false)->after('status');

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropIndex(['is_verified']);
            $table->dropColumn(['booking_id', 'is_verified']);
        });
    }
};
