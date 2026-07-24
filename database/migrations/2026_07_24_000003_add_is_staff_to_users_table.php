<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Staff (Jeff / Tessa) are global and bypass tenant scoping (spec §4). Spatie
// teams-mode roles are per-team, so global staff is a user flag rather than a
// null-team role (which Postgres cannot store — the pivot PK is NOT NULL).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_staff')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_staff');
        });
    }
};
