<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('booking_reference')->nullable();
            $table->string('property_code');
            $table->string('property_name');
            $table->string('provider')->default('ownerrez');
            $table->tinyInteger('rating'); // 1-5
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->tinyInteger('cleanliness_rating')->nullable();
            $table->tinyInteger('accuracy_rating')->nullable();
            $table->tinyInteger('communication_rating')->nullable();
            $table->tinyInteger('location_rating')->nullable();
            $table->tinyInteger('value_rating')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_reply')->nullable();
            $table->timestamp('admin_replied_at')->nullable();
            $table->timestamps();

            $table->index('property_code');
            $table->index('user_id');
            $table->index('status');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
