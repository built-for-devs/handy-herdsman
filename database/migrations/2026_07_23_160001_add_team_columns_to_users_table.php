<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Give users a current-team pointer for Spatie teams-mode context resolution
// and a phone for operational contact (§4, §5.7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_team_id')->nullable()->after('id')
                ->constrained('teams')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_team_id');
            $table->dropColumn(['phone', 'deleted_at']);
        });
    }
};
