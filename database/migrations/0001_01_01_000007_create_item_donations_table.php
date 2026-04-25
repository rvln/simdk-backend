<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('donation_id')->constrained('donations')->cascadeOnDelete();
            $table->foreignUuid('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->string('itemName_snapshot');
            $table->integer('qty');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_donations');
    }
};
