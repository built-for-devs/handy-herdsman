<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// payments — Cashier-managed. Payment method captured at booking, CHARGED
// when the booking reaches `confirmed`. Cash option skips the charge and
// marks owed. No Stripe invoicing, no tax (§5.6, §10b — Money).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            // Computed from rate_config: plan + per-cow + distance + receipt + storage.
            $table->json('line_items')->nullable();
            $table->decimal('total', 10, 2)->default(0);

            $table->string('method')->default('card'); // card | cash
            $table->string('stripe_payment_method_id')->nullable();
            $table->timestamp('charged_at')->nullable();

            // pending | paid | owed | refunded | failed
            $table->string('status')->default('pending')->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
