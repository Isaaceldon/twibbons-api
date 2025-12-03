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
        Schema::create('global_statistics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('total_users')->default(0);
            $table->unsignedBigInteger('active_users')->default(0); // Last 30 days
            $table->unsignedBigInteger('total_campaigns')->default(0);
            $table->unsignedBigInteger('active_campaigns')->default(0);
            $table->unsignedBigInteger('total_generations')->default(0);
            $table->unsignedBigInteger('total_downloads')->default(0);
            $table->unsignedBigInteger('total_ai_generations')->default(0);
            $table->unsignedBigInteger('total_video_jobs')->default(0);
            $table->date('stats_date')->unique();
            $table->timestamps();

            // Indexes
            $table->index('stats_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_statistics');
    }
};
