<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// cattle — animal_type drives protocol timing + breeding eligibility (§10b).
// "calf"/"weanling" are COMPUTED display labels from dob, never stored.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('reg_name')->nullable();
            $table->string('herd_number')->nullable();
            $table->date('dob')->nullable();
            $table->string('breed')->nullable();

            // heifer | cow | bull | steer (no "calf" — that is derived from dob).
            $table->string('animal_type');

            // A heifer auto-promotes to cow on her first recorded calving (§10b).
            $table->boolean('has_calved')->default(false);

            // active | inactive — inactive stops all pending reminders (§10b).
            $table->string('status')->default('active')->index();

            $table->boolean('a2a2')->nullable();

            // For-sale board (§5.9): client toggles + picks which fields are shared.
            $table->boolean('for_sale')->default(false);
            $table->json('for_sale_shared_fields')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattle');
    }
};
