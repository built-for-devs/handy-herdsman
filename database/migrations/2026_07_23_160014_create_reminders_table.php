<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// reminders — polymorphic (protocol/visit/cattle/client). category 1-4 sets
// channel resolution + quiet-hours behaviour. Non-urgent messages defer to
// the next allowed window; emergencies bypass (§5.7, §10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable'); // protocol | visit | cattle | client

            $table->timestamp('fire_at');
            $table->unsignedTinyInteger('category'); // 1-4 (§5.7)
            $table->string('channel')->nullable();   // sms | email | both (resolved)
            $table->string('template');
            $table->string('recipient_role')->default('owner');

            $table->timestamp('quiet_hours_deferred_to')->nullable();

            // pending | sent | cancelled (cancelled when animal set inactive, §10b)
            $table->string('status')->default('pending')->index();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
