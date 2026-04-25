<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->string('slot');
            $table->integer('quota');
            $table->integer('booked')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacities');
    }
};
