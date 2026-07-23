<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// protocols — a CIDR 10-day sync instance. V3 window is computed by animal
// type and ALWAYS recomputed from Visit 2's actual completed timestamp (§3,
// §10b). Same-type animals can share one protocol/V3 window; mixed cow+heifer
// cannot (handled at booking). All timestamps stored UTC.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->constrained();

            $table->string('plan_type'); // natural | sync
            $table->string('animal_type'); // cow | heifer — drives the V3 window

            $table->timestamp('visit1_at')->nullable();
            $table->timestamp('visit2_at')->nullable();
            $table->timestamp('visit3_window_start')->nullable();
            $table->timestamp('visit3_window_end')->nullable();

            // scheduled | v1_done | v2_done | completed | cancelled
            $table->string('status')->default('scheduled')->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocols');
    }
};
