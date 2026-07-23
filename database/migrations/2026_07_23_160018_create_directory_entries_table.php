<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// directory_entries — staff-curated local resource directory. SEO play; also
// where hoof trimmers are routed (not offered) (§5.8).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_entries', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index(); // vet | nutritionist | hoof_trimmer | ai_tech ...
            $table->string('name');
            $table->string('area')->nullable();
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_entries');
    }
};
