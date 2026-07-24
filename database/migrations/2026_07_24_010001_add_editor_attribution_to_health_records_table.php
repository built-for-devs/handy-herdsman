<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Records permissions (§10b): clients cannot edit staff-added records, but Jeff
// CAN edit client-added records — with the change attributed to him. The
// original author lives in added_by/added_role; the editor is captured here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $table->foreignId('edited_by')->nullable()->after('added_role')
                ->constrained('users')->nullOnDelete();
            $table->string('edited_role')->nullable()->after('edited_by'); // staff
            $table->timestamp('edited_at')->nullable()->after('edited_role');
        });
    }

    public function down(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edited_by');
            $table->dropColumn(['edited_role', 'edited_at']);
        });
    }
};
