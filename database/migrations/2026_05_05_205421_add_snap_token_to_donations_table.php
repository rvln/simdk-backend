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
        // Kolom order_id untuk Midtrans, unik tapi nullable (karena donasi non-finansial tidak memerlukannya)
        $table->string('order_id')->unique()->nullable()->after('id');
        // Snap token untuk pembayaran Midtrans
        $table->string('snap_token')->nullable()->after('status');
        // Jenis pembayaran (gopay, bank_transfer, dll.)
        $table->string('payment_type')->nullable()->after('snap_token');
    });
}

public function down()
{
    Schema::table('donations', function (Blueprint $table) {
        $table->dropColumn(['order_id', 'snap_token', 'payment_type']);
    });
}
};
