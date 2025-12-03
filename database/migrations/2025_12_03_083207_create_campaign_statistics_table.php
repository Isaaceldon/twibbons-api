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
        Schema::create('campaign_statistics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->unique()->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_generations')->default(0);
            $table->unsignedBigInteger('total_downloads')->default(0);
            $table->unsignedBigInteger('total_shares')->default(0);
            $table->json('countries')->nullable(); // {"FR": 120, "US": 89, ...}
            $table->json('devices')->nullable(); // {"mobile": 200, "desktop": 150, ...}
            $table->json('platforms')->nullable(); // Share platforms breakdown
            $table->json('top_referrers')->nullable(); // Where traffic comes from
            $table->date('stats_date')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('campaign_id');
            $table->index('total_views');
            $table->index('total_generations');
            $table->index('stats_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_statistics');
    }
};
