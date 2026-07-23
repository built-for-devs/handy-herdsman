<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// supplies — Jeff's own working stock (CIDRs, GnRH, PG, sleeves, tags...).
// Low-stock thresholds alert; unit_cost feeds COGS (§5.6b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('item');
            $table->string('category')->nullable();
            $table->string('unit')->nullable(); // each | dose | ml ...
            $table->decimal('on_hand', 10, 2)->default(0);
            $table->decimal('low_stock_threshold', 10, 2)->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->boolean('is_prescription')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
