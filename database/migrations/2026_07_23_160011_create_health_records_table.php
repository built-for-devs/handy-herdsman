<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// health_records — per-animal record log. Clients CANNOT edit/delete
// staff-added records; Jeff CAN edit client-added records, attributed (§10b).
// BCS entries store a 1-9 score for the BCS-vs-conception report (§5.5, §5.6c).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cattle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();

            // vaccination | treatment | nutrition | general | dehorning |
            // preg_check | body_condition | ...
            $table->string('type')->index();
            $table->json('payload')->nullable();

            // Body Condition Score 1-9 (5-6 target), captured on breeding visits.
            $table->unsignedTinyInteger('bcs_score')->nullable();
            $table->timestamp('recorded_at')->nullable();

            // Attribution for the edit-permission rules (§10b).
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('added_role')->nullable(); // staff | owner | member

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_records');
    }
};
