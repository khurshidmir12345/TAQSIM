<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 8)->unique()->comment('ISO-like: UZS, USD, KZT...');
            $table->string('name', 64);
            $table->string('symbol', 8)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['code' => 'UZS', 'name' => "O'zbek so'mi", 'symbol' => "so'm", 'sort_order' => 10],
            ['code' => 'KZT', 'name' => 'Qozog‘iston tenge', 'symbol' => '₸', 'sort_order' => 20],
            ['code' => 'KGS', 'name' => 'Qirg‘iz somi', 'symbol' => 'с', 'sort_order' => 30],
            ['code' => 'RUB', 'name' => 'Rossiya rubli', 'symbol' => '₽', 'sort_order' => 40],
            ['code' => 'TJS', 'name' => 'Tojik somoni', 'symbol' => 'сом.', 'sort_order' => 50],
            ['code' => 'USD', 'name' => 'AQSH dollari', 'symbol' => '$', 'sort_order' => 60],
        ];

        foreach ($rows as $row) {
            DB::table('currencies')->insert([
                'id'         => (string) Str::uuid(),
                'code'       => $row['code'],
                'name'       => $row['name'],
                'symbol'     => $row['symbol'],
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
