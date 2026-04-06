<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE property_listings MODIFY COLUMN provider ENUM('hotelbeds','ownerrez','direct') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE property_listings MODIFY COLUMN provider ENUM('hotelbeds','ownerrez') NOT NULL");
    }
};
