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
        Schema::create('statistics_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->date('stats_date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('generations')->default(0);
            $table->unsignedInteger('downloads')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->json('details')->nullable(); // Hourly breakdown, countries, devices
            $table->timestamps();

            // Unique constraint - one record per campaign per day
            $table->unique(['campaign_id', 'stats_date']);

            // Indexes
            $table->index('campaign_id');
            $table->index('stats_date');
            $table->index(['stats_date', 'campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistics_daily');
    }
};
