<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// services — source of truth for the services page, pricing, and the booking
// flow. `type` routes the flow; adding a service later is data entry, not a
// deploy (§5.2, §6, §7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->index();

            // protocol | oncall | standard — routes the booking flow (§5.2).
            $table->string('type');

            // Editable price/fee rule, e.g.
            // { "model": "flat", "price": 300, "per_head": 100, "visit_minimum": 50 }
            $table->json('price_rule')->nullable();

            $table->boolean('requires_first_time_review')->default(true);
            $table->boolean('requires_containment')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
