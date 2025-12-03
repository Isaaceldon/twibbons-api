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
        Schema::create('shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_generated_image_id')->constrained('user_generated_images')->onDelete('cascade');
            $table->string('platform', 50); // facebook, twitter, instagram, linkedin, whatsapp
            $table->string('share_url')->nullable();
            $table->json('metadata')->nullable(); // platform-specific data
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 10)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_generated_image_id');
            $table->index('platform');
            $table->index('country');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};
