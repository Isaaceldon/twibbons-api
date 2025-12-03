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
        Schema::create('campaign_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->foreignUuid('ai_generation_id')->nullable()->constrained('ai_generations')->onDelete('set null');
            $table->enum('type', ['image', 'video'])->default('image');
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->json('metadata')->nullable(); // format, colors, elements, etc.
            $table->unsignedInteger('width')->default(1080);
            $table->unsignedInteger('height')->default(1080);
            $table->unsignedBigInteger('file_size')->default(0); // bytes
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('campaign_id');
            $table->index('ai_generation_id');
            $table->index('type');
            $table->index('is_primary');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_templates');
    }
};
