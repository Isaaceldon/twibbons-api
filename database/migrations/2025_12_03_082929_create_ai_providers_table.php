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
        Schema::create('ai_providers', function (Blueprint $table) {
           $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('api_endpoint');
            $table->text('api_key_encrypted');
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(1); // 1 = highest priority
            $table->unsignedInteger('rate_limit')->default(100); // requests per hour
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('is_active');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
