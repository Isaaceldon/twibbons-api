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
        Schema::create('brand_kit_campaign', function (Blueprint $table) {
             $table->uuid('id')->primary();
            $table->foreignUuid('brand_kit_id')->constrained('brand_kits')->onDelete('cascade');
            $table->foreignUuid('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->json('applied_settings')->nullable(); // specific settings used for this campaign
            $table->timestamps();

            // Unique constraint
            $table->unique(['brand_kit_id', 'campaign_id']);

            // Indexes
            $table->index('brand_kit_id');
            $table->index('campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_kit_campaign');
    }
};
