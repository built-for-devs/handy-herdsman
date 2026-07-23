<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// visit_completions — the keystone record. The authoritative completed_at is
// what V3 recomputes from. Drives inventory decrements, cattle records, and
// COGS. Mobile-first form that persists a local draft + syncs (§5.5, §7.x, §10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();

            $table->timestamp('completed_at'); // authoritative timestamp (UTC)
            $table->boolean('procedure_confirmed')->default(false);

            // AI visits: which straw/sire was used -> decrement inventory by 1.
            $table->foreignId('semen_inventory_id')->nullable()->constrained('semen_inventory')->nullOnDelete();
            $table->unsignedInteger('straws_used')->default(0);
            $table->unsignedInteger('straws_wasted')->default(0); // logged separately as waste

            // Supplies consumed (from the service usage profile, adjustable):
            // { "supply_id": qty, ... }
            $table->json('supplies_used')->nullable();

            $table->decimal('mileage', 8, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_completions');
    }
};
