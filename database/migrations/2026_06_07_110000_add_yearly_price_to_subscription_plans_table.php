<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Yillik narx (USD). null bo'lsa — bu tarifda yillik obuna yo'q.
            $table->decimal('price_usd_yearly', 10, 2)->nullable()->after('price_usd');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('price_usd_yearly');
        });
    }
};
