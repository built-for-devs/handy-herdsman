<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// redirects — 301 map from old Homestead Herds URLs to migrated blog posts, so
// the content migration preserves link equity (§8, §10b). Populated by the
// one-off content:migrate-ghost command; served by a route fallback.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_url')->nullable();
            $table->unsignedSmallInteger('status')->default(301);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
