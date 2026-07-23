<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// posts — SEO/nurture blog. Migrated posts publish with NEW dates and keep an
// old_url for redirects to preserve link equity (§5.3, §8, §10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();

            // SEO meta.
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Redirect source for migrated Ghost posts (§10b — Content migration).
            $table->string('old_url')->nullable()->index();

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
