<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// protocol_cattle — same-type animals (all cows OR all heifers) can share one
// protocol and V3 window (§10b — Multi-animal bookings).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_cattle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cattle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['protocol_id', 'cattle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_cattle');
    }
};
