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
        Schema::create('user_generated_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null'); // nullable for guests
            $table->foreignUuid('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->foreignUuid('campaign_template_id')->constrained('campaign_templates')->onDelete('cascade');
            $table->string('photo_path'); // User's uploaded photo
            $table->string('result_path'); // Final twibbon with frame
            $table->json('customizations')->nullable(); // text, stickers, adjustments applied
            $table->string('device_type', 50)->nullable(); // mobile, desktop, tablet
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 10)->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('campaign_template_id');
            $table->index('country');
            $table->index('device_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_generated_images');
    }
};
