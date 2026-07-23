<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// clients — the client record for a team. status gates self-booking (§10b):
// new -> active (after one completed visit) -> inactive (after 1yr idle).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('contact_name');
            $table->string('email');   // REQUIRED — account identity / login (§5.7)
            $table->string('phone');   // REQUIRED — operational, Jeff drives out (§5.7)

            // Address + cached geocoding (geocoded once at onboarding, §5.10).
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('cached_distance_miles', 6, 2)->nullable();
            $table->boolean('in_range')->nullable();

            // new | active | inactive (§10b — Client status).
            $table->string('status')->default('new')->index();

            // SMS consent is a SEPARATE, timestamped, per-category opt-in (§5.7).
            // { "transactional": "2026-07-01T...", "promotional": null, ... }
            $table->json('consent_sms')->nullable();

            // 4 categories x channel (email|text|both); at least one on (§5.7).
            $table->json('channel_prefs')->nullable();

            // Staff channel override wins over client prefs; carries a note (§5.7).
            $table->json('staff_channel_override')->nullable();
            $table->text('staff_channel_override_note')->nullable();

            $table->timestamp('agreement_signed_at')->nullable();
            $table->timestamp('waiver_signed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable(); // drives 1yr idle -> inactive

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
