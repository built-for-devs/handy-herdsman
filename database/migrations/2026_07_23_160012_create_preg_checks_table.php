<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// preg_checks — blood is a two-stage async event (pending -> final); palpation
// (after 4mo) is immediate. Downstream nurture fires ONLY on a final result
// (§10b — Preg check results). Jeff records results, not clients.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preg_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cattle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();

            $table->string('method'); // blood | palpation
            $table->boolean('lab_requested')->default(false); // optional +$15 confirmation

            // pending (blood, awaiting lab) -> open | bred | recheck (final)
            $table->string('state')->default('pending')->index();

            $table->timestamp('result_recorded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preg_checks');
    }
};
