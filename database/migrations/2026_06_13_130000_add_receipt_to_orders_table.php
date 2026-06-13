<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Balans to'ldirish so'rovi uchun: chek (kvitansiya) rasmi (private disk path)
     * va admin rad etganda sabab.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('payment_method');
            $table->string('reject_reason')->nullable()->after('receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'reject_reason']);
        });
    }
};
