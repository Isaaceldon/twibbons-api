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
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('campaign_id')->nullable()->constrained('campaigns')->onDelete('set null');
            $table->foreignUuid('ai_provider_id')->constrained('ai_providers')->onDelete('cascade');
            $table->text('prompt');
            $table->string('logo_path')->nullable();
            $table->json('parameters')->nullable(); // size, style, colors, etc.
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('results')->nullable(); // array of generated image URLs
            $table->decimal('generation_time', 8, 2)->nullable(); // seconds
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('ai_provider_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
