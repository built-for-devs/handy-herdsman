<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cattle-for-sale board (§5.9): who listed the animal — the client (their own
// herd) or staff posting cattle Jeff knows others want to sell. Null until an
// animal is listed for sale.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            $table->string('for_sale_listed_by_role')->nullable()->after('for_sale_shared_fields');
        });
    }

    public function down(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            $table->dropColumn('for_sale_listed_by_role');
        });
    }
};
