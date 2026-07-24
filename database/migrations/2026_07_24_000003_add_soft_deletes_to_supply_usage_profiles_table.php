<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 #231 — usage profiles soft-delete like everything else, and gain a label
// + notes so staff can describe what a service's default consumption is (§5.6b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_usage_profiles', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('consumables');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('supply_usage_profiles', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->dropSoftDeletes();
        });
    }
};
