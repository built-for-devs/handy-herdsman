<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// rate_config — editable pricing + fee rules (plan prices, per-cow add-on,
// distance threshold/amount, receipt fee, storage fee, free-year rule). No
// deploy to change a price (§2, §7). Key/value so keys can be added freely.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_config', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('label')->nullable();
            $table->string('group')->nullable(); // breeding | fees | storage ...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_config');
    }
};
