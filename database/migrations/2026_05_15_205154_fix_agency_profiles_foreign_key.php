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
        Schema::table('agency_profiles', function (Blueprint $table) {
            // Drop the broken foreign key that references 'users1' instead of 'users'
            // Using raw statement because Laravel can't drop a FK pointing to wrong table
            $table->dropForeign(['user_id']);
        });

        Schema::table('agency_profiles', function (Blueprint $table) {
            // Recreate the foreign key pointing to the correct 'users' table
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - the fix is the correct state
    }
};
