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
        Schema::create('video_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('video_path'); // Path to template video file
            $table->unsignedInteger('duration'); // Duration in seconds
            $table->enum('format', ['1:1', '9:16', '16:9'])->default('1:1');
            $table->unsignedInteger('width')->default(1080);
            $table->unsignedInteger('height')->default(1080);
            $table->json('overlay_zones')->nullable(); // Zones where logo/text can be placed
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('is_active');
            $table->index('format');
            $table->index('usage_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_templates');
    }
};
