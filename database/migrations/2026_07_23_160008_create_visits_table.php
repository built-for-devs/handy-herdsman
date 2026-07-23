<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// visits — a single scheduled farm call. Belongs to a protocol for sync
// visits (V1/V2/V3), standalone otherwise. Mileage + fee tracked per visit
// for COGS (§5.5, §5.6c, §6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cattle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('protocol_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type'); // v1 | v2 | v3 | standard | oncall
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('staff_notes')->nullable();
            $table->decimal('mileage', 8, 2)->nullable();
            $table->boolean('fee_applied')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
