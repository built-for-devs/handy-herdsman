<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// booking_cattle — a booking can cover multiple animals for standard/per-head
// services. For AI/protocol, mixed cow+heifer is blocked at booking (§10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_cattle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cattle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['booking_id', 'cattle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_cattle');
    }
};
