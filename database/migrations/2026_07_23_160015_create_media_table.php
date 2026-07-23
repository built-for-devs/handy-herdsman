<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// media — polymorphic (cattle/visit). Clients upload to profiles (for-sale);
// Jeff uploads at appointments (healing, BCS comparison). Chronological (§10b).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable'); // cattle | visit
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_role')->nullable(); // staff | owner | member

            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamp('taken_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
