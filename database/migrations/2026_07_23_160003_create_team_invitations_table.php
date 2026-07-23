<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// team_invitations — owner invites a member (spouse) or vet. Vet scope is
// client-controlled per invitation (§4, §10b — records/permissions).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role'); // member | vet
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending'); // pending | accepted | revoked

            // Client-selected vet read scope: e.g.
            // { "profile": true, "record_types": ["vaccination","preg_check"] }
            $table->json('vet_scope')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
