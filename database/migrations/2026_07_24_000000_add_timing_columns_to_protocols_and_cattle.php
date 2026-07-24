<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M1 timing engine additions (§3, §5.4b, §10b):
//  - protocols: store the recommended V3 time (window midpoint) and flag when a
//    recomputed V3 lands outside working hours so it surfaces to Jeff (1.3).
//  - cattle: store the estimated due date once a pregnancy is confirmed, to
//    drive the calving-countdown reminders (1.4 / §9.3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            $table->timestamp('visit3_recommended_at')->nullable()->after('visit3_window_end');
            $table->boolean('visit3_conflict')->default(false)->after('visit3_recommended_at');
            $table->string('visit3_conflict_reason')->nullable()->after('visit3_conflict');
        });

        Schema::table('cattle', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('has_calved');
        });
    }

    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            $table->dropColumn(['visit3_recommended_at', 'visit3_conflict', 'visit3_conflict_reason']);
        });

        Schema::table('cattle', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
