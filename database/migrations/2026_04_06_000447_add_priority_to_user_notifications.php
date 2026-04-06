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
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->enum('priority', ['low','normal','high','urgent'])->default('normal')->after('type');
            $table->string('category')->nullable()->after('priority')
                  ->comment('booking|payment|system|promo|review');
            $table->string('action_url')->nullable()->after('category');
            $table->string('icon')->nullable()->after('action_url');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropColumn(['priority','category','action_url','icon','deleted_at']);
        });
    }
};
