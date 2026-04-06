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
        Schema::table('content_pages', function (Blueprint $table) {
            // SEO fields
            $table->string('og_title')->nullable()->after('meta_description');
            $table->string('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            $table->string('canonical_url')->nullable()->after('og_image');
            $table->boolean('no_index')->default(false)->after('canonical_url');
            $table->json('schema_markup')->nullable()->after('no_index')
                  ->comment('JSON-LD structured data');

            // Page builder
            $table->string('template')->default('default')->after('type')
                  ->comment('Which frontend template to use: default|landing|minimal|full-width');
            $table->string('featured_image')->nullable()->after('template');
            $table->json('sections')->nullable()->after('featured_image')
                  ->comment('Flexible page builder sections array');

            // Nav/hierarchy
            $table->string('parent_slug')->nullable()->after('slug');
            $table->integer('sort_order')->default(0)->after('parent_slug');
            $table->boolean('show_in_nav')->default(false)->after('sort_order');
            $table->string('nav_label')->nullable()->after('show_in_nav');
            $table->string('nav_icon')->nullable()->after('nav_label');

            // Soft delete
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('content_pages', function (Blueprint $table) {
            $table->dropColumn([
                'og_title','og_description','og_image','canonical_url','no_index','schema_markup',
                'template','featured_image','sections',
                'parent_slug','sort_order','show_in_nav','nav_label','nav_icon',
                'deleted_at',
            ]);
        });
    }
};
