<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Photo/media uploads (§228, §10b): a photo always attaches to the animal
// (the `mediable` morph) and OPTIONALLY references the visit it was taken at
// (healing progress, condition comparison). Anchoring to the animal keeps the
// gallery chronological; the visit link is nullable metadata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->after('mediable_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visit_id');
        });
    }
};
