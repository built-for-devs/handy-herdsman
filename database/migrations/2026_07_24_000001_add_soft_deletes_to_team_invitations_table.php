<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// team_invitations — everything soft-deletes (audit/history preserved). Also
// track who invited and which user accepted (spec §4, §10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->foreignId('invited_by')->nullable()->after('team_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->after('vet_scope')
                ->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropSoftDeletes();
        });
    }
};
