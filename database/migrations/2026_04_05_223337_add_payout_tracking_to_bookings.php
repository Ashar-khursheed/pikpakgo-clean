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
        // Fix provider enum to include 'direct'
        \DB::statement("ALTER TABLE bookings MODIFY COLUMN provider ENUM('hotelbeds','ownerrez','direct') NOT NULL");

        Schema::table('bookings', function (Blueprint $table) {
            // Provider payout tracking
            $table->enum('provider_payout_status', ['pending', 'sent', 'failed', 'not_required'])
                  ->default('pending')
                  ->after('provider_booking_reference');

            $table->timestamp('provider_payout_at')->nullable()->after('provider_payout_status');
            $table->text('provider_payout_error')->nullable()->after('provider_payout_at');

            // Financial breakdown stored explicitly for reporting
            $table->decimal('net_profit', 10, 2)->default(0)->after('tax_amount')
                  ->comment('markup_amount = total_price - base_price = our profit per booking');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['provider_payout_status', 'provider_payout_at', 'provider_payout_error', 'net_profit']);
        });
        \DB::statement("ALTER TABLE bookings MODIFY COLUMN provider ENUM('hotelbeds','ownerrez') NOT NULL");
    }
};
