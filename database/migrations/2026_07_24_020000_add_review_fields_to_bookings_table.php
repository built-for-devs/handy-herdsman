<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M6 booking additions (§5.5, §10b):
//  - review_reason: why a booking needs staff review — first-time client, or a
//    blackout dropped over an in-flight protocol that cannot be auto-rebooked
//    (surfaces to Jeff as a manual decision, never auto-moved).
//  - reviewed_by: the staff user who confirmed/declined a provisional booking.
//  - decline_reason: staff note when a provisional booking is declined.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('review_reason')->nullable()->after('requires_review');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('decline_reason')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_reason', 'decline_reason']);
        });
    }
};
