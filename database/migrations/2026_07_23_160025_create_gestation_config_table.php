<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// gestation_config — breed -> average gestation days (default 283). Drives the
// due-date calculator + calving reminders. Heifer offset stored as the special
// row `__heifer_offset__` (§5.4b, §7). Editable, no deploy.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestation_config', function (Blueprint $table) {
            $table->id();
            $table->string('breed')->unique();
            $table->smallInteger('gestation_days'); // days; offset row may be negative
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestation_config');
    }
};
