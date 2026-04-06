<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignUuid('currency_id')
                ->nullable()
                ->after('price_per_unit')
                ->constrained('currencies')
                ->restrictOnDelete();
        });

        $uzsId = DB::table('currencies')->where('code', 'UZS')->value('id');

        $rows = DB::table('ingredients')->whereNull('currency_id')->get();
        foreach ($rows as $row) {
            $shopCurrencyId = DB::table('shops')->where('id', $row->shop_id)->value('currency_id');
            $cid = $shopCurrencyId ?? $uzsId;
            if ($cid !== null) {
                DB::table('ingredients')->where('id', $row->id)->update(['currency_id' => $cid]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};
