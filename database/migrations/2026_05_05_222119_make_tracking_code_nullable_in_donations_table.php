<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('donations', function (Blueprint $table) {
        // Mengubah kolom menjadi nullable agar menerima nilai kosong saat inisiasi Midtrans
        $table->string('tracking_code')->nullable()->change();
    });
}

public function down()
{
    Schema::table('donations', function (Blueprint $table) {
        // Rollback ke strict mode jika diperlukan
        $table->string('tracking_code')->nullable(false)->change();
    });
}
};
