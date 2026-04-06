<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $uzsId = DB::table('currencies')->where('code', 'UZS')->value('id');
        if ($uzsId === null) {
            return;
        }

        DB::table('shops')
            ->whereNull('currency_id')
            ->update(['currency_id' => $uzsId]);
    }

    public function down(): void
    {
        // no-op
    }
};
