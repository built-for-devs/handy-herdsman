<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// availability_rules — Jeff's operational availability the scheduler reads:
// per-weekday working hours, max visits/day, inter-appointment buffer. Sundays
// off by default; no Sunday mornings ever; emergencies bypass (§7, §10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sun .. 6=Sat
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedTinyInteger('max_visits_per_day')->default(6);
            $table->unsignedInteger('buffer_minutes')->default(30); // travel between calls
            $table->timestamps();
            $table->unique('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
