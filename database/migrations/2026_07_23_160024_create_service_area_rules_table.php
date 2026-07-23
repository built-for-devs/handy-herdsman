<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// service_area_rules — mileage thresholds, fee tiers, declined zones. All
// distance math originates from the farm (§2, §10b). Editable, no deploy.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_area_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // fee_tier | declined_zone
            $table->decimal('min_miles', 6, 2)->nullable();
            $table->decimal('max_miles', 6, 2)->nullable();
            $table->decimal('fee', 10, 2)->nullable();     // flat fee for the tier
            $table->string('zone_label')->nullable();       // for declined zones
            $table->boolean('declined')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_area_rules');
    }
};
