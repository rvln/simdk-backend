<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * visit_reports — UGC Visit Reports (Two-Step Moderation Workflow)
     *
     * Constraint: A report can only be created if the associated
     * Visit has status = COMPLETED (enforced at Service layer).
     */
    public function up(): void
    {
        Schema::create('visit_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->json('image_path')->nullable();
            $table->string('status')->default('PENDING'); // ReportStatusEnum: PENDING, PUBLISHED, REJECTED
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            // One report per visit per user
            $table->unique(['visit_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_reports');
    }
};
