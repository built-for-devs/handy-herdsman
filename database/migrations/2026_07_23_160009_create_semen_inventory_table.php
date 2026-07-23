<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// semen_inventory — a CUSTODY/STORAGE ledger. We do NOT sell straws; owned_by
// is always the client. Straws never expire (§5.6, §10b). Two sourcing paths:
// client-sourced (needs our intake info) and Jeff-sourced (capture bank/bull).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semen_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('sire')->nullable();
            $table->string('breed')->nullable();
            $table->unsignedInteger('straws_count')->default(0);

            $table->string('source'); // client | jeff
            $table->string('source_contact')->nullable(); // bank/bull/contact/who-to-pay
            $table->string('location')->nullable();        // tank / canister / location

            $table->string('owned_by')->default('client'); // always client

            $table->date('storage_start')->nullable();
            $table->date('storage_free_until')->nullable(); // free yr 1 with AI, then $50/yr
            $table->boolean('receipt_fee_charged')->default(false); // $15 per shipment

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semen_inventory');
    }
};
