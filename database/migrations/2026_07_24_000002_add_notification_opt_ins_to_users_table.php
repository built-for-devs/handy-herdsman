<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// users.notification_opt_ins — invited members receive NO automated
// notifications until they opt in per category; the owner receives all by
// default and vets never receive automated notifications (spec §10b).
// Shape: { "1": true, "3": true } keyed by category number.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_opt_ins')->nullable()->after('current_team_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_opt_ins');
        });
    }
};
