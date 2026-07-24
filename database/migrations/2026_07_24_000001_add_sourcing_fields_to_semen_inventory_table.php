<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 #230 — extend the semen custody lot with the two sourcing paths (§5.6):
//   * client-sourced: our intake info; straws must arrive BEFORE the protocol
//   * Jeff-sourced:   farm/bank, bull, contact, who to pay
// Storage is free in year 1 only WITH AI, so track whether the lot is tied to
// an AI plan. Straws NEVER expire (§10b) — deliberately no expiry column.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semen_inventory', function (Blueprint $table) {
            // Jeff-sourced ordering details.
            $table->string('source_farm')->nullable()->after('source');
            $table->string('bull_info')->nullable()->after('source_farm');
            $table->string('pay_to')->nullable()->after('source_contact');

            // Client-sourced intake info (what we hand the client to ship us).
            $table->text('intake_shipping_address')->nullable()->after('location');
            $table->string('tank_details')->nullable()->after('intake_shipping_address');
            $table->date('expected_arrival')->nullable()->after('tank_details');
            $table->date('arrived_at')->nullable()->after('expected_arrival');

            // Storage is free in year 1 only when paired with an AI plan.
            $table->boolean('with_ai')->default(false)->after('storage_free_until');
        });
    }

    public function down(): void
    {
        Schema::table('semen_inventory', function (Blueprint $table) {
            $table->dropColumn([
                'source_farm', 'bull_info', 'pay_to',
                'intake_shipping_address', 'tank_details', 'expected_arrival', 'arrived_at',
                'with_ai',
            ]);
        });
    }
};
