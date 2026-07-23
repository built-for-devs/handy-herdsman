<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// bookings — book immediately; first-timers are provisional pending staff
// review (24h SLA); established clients self-confirm (§5.5, §10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('proposed_start')->nullable();

            // Computed V2/V3 windows for protocol bookings (§3).
            $table->json('computed_windows')->nullable();

            // provisional | confirmed | declined | cancelled (§10b).
            $table->string('status')->default('provisional')->index();
            $table->boolean('requires_review')->default(true);
            $table->boolean('is_oncall')->default(false);

            // Distance fee is PER BOOKING/PROTOCOL, not per visit (§10b — Money).
            $table->boolean('distance_fee_flag')->default(false);

            // Cash-in-person skips the charge and marks amount owed (§10b).
            $table->boolean('is_cash')->default(false);

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
