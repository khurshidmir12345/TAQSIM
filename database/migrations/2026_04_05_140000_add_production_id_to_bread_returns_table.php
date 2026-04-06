<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bread_returns', function (Blueprint $table) {
            $table->foreignUuid('production_id')
                ->nullable()
                ->after('bread_category_id')
                ->constrained('productions')
                ->cascadeOnDelete();
        });

        $this->backfillProductionIds();
    }

    private function backfillProductionIds(): void
    {
        $rows = DB::table('bread_returns')->whereNull('production_id')->get();

        foreach ($rows as $r) {
            $pid = DB::table('productions')
                ->where('shop_id', $r->shop_id)
                ->where('bread_category_id', $r->bread_category_id)
                ->whereDate('date', $r->date)
                ->orderByDesc('created_at')
                ->value('id');

            if ($pid !== null) {
                DB::table('bread_returns')->where('id', $r->id)->update(['production_id' => $pid]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bread_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_id');
        });
    }
};
