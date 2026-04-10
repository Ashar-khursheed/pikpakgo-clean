<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_configs', function (Blueprint $table) {
            $table->id();

            // Route identity
            $table->string('route_slug')->unique()->comment('Unique key: home, properties, booking, booking-confirm …');
            $table->string('route_path')->nullable()->comment('Frontend path: /, /properties, /booking');
            $table->string('route_label')->nullable()->comment('Human label: Homepage, Properties Listing …');
            $table->string('route_group')->default('General')->comment('Core | Search | Booking | Account | Blog | Dynamic');

            // Core SEO
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();

            // Open Graph
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image', 500)->nullable();

            // Twitter Card
            $table->string('twitter_card', 50)->default('summary_large_image');
            $table->string('twitter_title', 255)->nullable();
            $table->string('twitter_description', 500)->nullable();
            $table->string('twitter_image', 500)->nullable();

            // Technical SEO
            $table->string('canonical_url', 500)->nullable();
            $table->boolean('no_index')->default(false);
            $table->boolean('no_follow')->default(false);

            // Structured data (JSON-LD)
            $table->json('schema_markup')->nullable()->comment('JSON-LD structured data object');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_configs');
    }
};
