<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M9 (#244-#247) — the reminder/notification engine columns:
//  - `payload`     : template context (blog post link, milestone label, the
//                    insemination date a +60d follow-up suppresses against).
//  - `dedupe_key`  : idempotency guard so a daily sweep or re-fired trigger
//                    never schedules the same reminder twice (§5.7).
//  - soft deletes  : nothing hard-deletes — reminders keep audit history (§10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('recipient_role');
            $table->string('dedupe_key')->nullable()->unique()->after('payload');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropColumn(['payload', 'dedupe_key', 'deleted_at']);
        });
    }
};
