<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// supply_usage_profiles — default consumables per service, auto-decremented on
// appointment completion and manually adjustable (§5.6b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_usage_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            // Default consumables + quantities, e.g. { "supply_id": qty, ... }
            // (sync protocol => 1 CIDR + 2 GnRH + 1 PG + sleeves/gloves).
            $table->json('consumables');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_usage_profiles');
    }
};
