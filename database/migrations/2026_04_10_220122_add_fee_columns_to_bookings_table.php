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
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('cleaning_fee', 10, 2)->default(0)->after('tax_amount');
            $table->decimal('service_fee', 10, 2)->default(0)->after('cleaning_fee');
            $table->decimal('damage_deposit', 10, 2)->default(0)->after('service_fee');
            $table->json('other_fees')->nullable()->after('damage_deposit');
            $table->json('fees_breakdown')->nullable()->after('other_fees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['cleaning_fee', 'service_fee', 'damage_deposit', 'other_fees', 'fees_breakdown']);
        });
    }
};
