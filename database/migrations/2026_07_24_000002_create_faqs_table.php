<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// faqs — public FAQ page (§5.1, issue 2.8). Straw-cost REFERENCE ranges are
// rendered from rate_config (not stored here) and labeled as industry
// reference, explicitly NOT our prices. Soft-deletes like everything else.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('general')->index();
            $table->string('question');
            $table->text('answer');
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
