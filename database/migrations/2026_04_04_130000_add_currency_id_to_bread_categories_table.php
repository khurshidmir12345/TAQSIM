<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bread_categories', function (Blueprint $table) {
            $table->foreignUuid('currency_id')
                ->nullable()
                ->after('selling_price')
                ->constrained('currencies')
                ->restrictOnDelete();
        });

        $uzsId = DB::table('currencies')->where('code', 'UZS')->value('id');

        $rows = DB::table('bread_categories')->whereNull('currency_id')->get();
        foreach ($rows as $bc) {
            $shopCurrencyId = DB::table('shops')->where('id', $bc->shop_id)->value('currency_id');
            $cid = $shopCurrencyId ?? $uzsId;
            if ($cid !== null) {
                DB::table('bread_categories')->where('id', $bc->id)->update(['currency_id' => $cid]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bread_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};
