<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('capacities', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            // Note: `quota` should logically be treated as 1 moving forward (no need to change the column type, just its operational logic).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capacities', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
