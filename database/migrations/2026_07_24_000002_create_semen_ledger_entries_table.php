<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 #230 — append-only custody ledger. Every movement of a client's straws is
// a row here so custody history is fully auditable (§5.6, §10b). Movements:
// received | stored | used | transferred. Fees (receipt/storage) are recorded
// on the entry that incurs them. Soft-deletes like everything else.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semen_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semen_inventory_id')->constrained('semen_inventory')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // received | stored | used | transferred
            $table->integer('straws_delta')->default(0); // signed: +received, -used/-transferred

            // Fee incurred by this movement, if any (config-driven amounts).
            $table->string('fee_type')->nullable();       // receipt | storage
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->unsignedSmallInteger('storage_year')->nullable(); // which storage year a storage fee covers

            $table->string('location_from')->nullable();
            $table->string('location_to')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['semen_inventory_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semen_ledger_entries');
    }
};
