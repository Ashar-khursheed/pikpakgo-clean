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
        Schema::create('rewards_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('points'); // positive for earn, negative for redeem/rollback
            $table->string('type'); // earn, redeem, rollback, bonus
            $table->string('tier_applied')->nullable(); // silver, gold, platinum
            $table->text('description')->nullable();
            $table->timestamps();

            // Indexes for speed
            $table->index('user_id');
            $table->index('booking_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewards_ledger');
    }
};
