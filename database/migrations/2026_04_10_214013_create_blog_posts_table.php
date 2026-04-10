<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('blog_category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // Content
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable()->comment('HTML / Markdown content');
            $table->string('featured_image')->nullable();
            $table->json('gallery')->nullable()->comment('Array of image URLs');
            $table->json('tags')->nullable()->comment('Array of tag strings');

            // Status & scheduling
            $table->enum('status', ['draft', 'published', 'scheduled', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();

            // Meta
            $table->unsignedSmallInteger('read_time')->default(0)->comment('Estimated read time in minutes');
            $table->unsignedInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('allow_comments')->default(true);

            // SEO
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('twitter_title', 255)->nullable();
            $table->string('twitter_description', 500)->nullable();
            $table->string('twitter_image', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->boolean('no_index')->default(false);
            $table->json('schema_markup')->nullable()->comment('JSON-LD for this post (Article / BlogPosting)');

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['status', 'published_at']);
            $table->index('is_featured');
            $table->index('blog_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
