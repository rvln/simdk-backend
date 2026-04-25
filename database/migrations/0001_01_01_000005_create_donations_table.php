<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('donorName');
            $table->string('donorEmail');
            $table->string('donorPhone');
            $table->string('type');
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('status')->default(\App\Enums\DonationStatusEnum::PENDING->value);
            $table->string('tracking_code')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
