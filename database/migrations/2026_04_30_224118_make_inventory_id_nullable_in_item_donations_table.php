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
        Schema::table('item_donations', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('inventory_id');
            $table->dropForeign(['inventory_id']);
            $table->uuid('inventory_id')->nullable()->change();
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_donations', function (Blueprint $table) {
            $table->dropForeign(['inventory_id']);
            $table->dropColumn('item_name');
            $table->uuid('inventory_id')->nullable(false)->change();
            $table->foreign('inventory_id')->references('id')->on('inventories')->cascadeOnDelete();
        });
    }
};
