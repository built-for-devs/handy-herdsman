<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// posts.feature_image — re-hosted hero image path for a post. The content
// migration downloads the old Ghost image and stores a local path here rather
// than hotlinking the old domain (§10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('feature_image')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('feature_image');
        });
    }
};
