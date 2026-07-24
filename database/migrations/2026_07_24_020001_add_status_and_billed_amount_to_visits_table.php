<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M6 booking additions (§10b — Rescheduling, failed visits, staff override):
//  - status: scheduled | completed | failed | cancelled. A failed/aborted visit
//    (cow won't load, no chute, not contained) is STILL billed at the normal
//    visit rate — Jeff's judgment via manual override, no automated trip-fee.
//  - billed_amount: the charge for this visit. Defaults from rate_config; Jeff
//    can edit it (and every other field) directly — the escape hatch for all
//    irregular cases.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('status')->default('scheduled')->after('type')->index();
            $table->decimal('billed_amount', 10, 2)->nullable()->after('fee_applied');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['status', 'billed_amount']);
        });
    }
};
