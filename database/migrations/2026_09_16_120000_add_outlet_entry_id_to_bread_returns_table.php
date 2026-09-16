<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Do'kondan qaytgan mahsulot oddiy vozvrat sifatida yoziladi — shunda u
 * asosiy sahifa, tarix, statistika va kassada bir xil ayriladi. Bu ustun
 * vozvratni do'kon daftaridagi qatorga bog'laydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bread_returns', function (Blueprint $table) {
            $table->foreignUuid('outlet_entry_id')
                ->nullable()
                ->after('production_id')
                ->constrained('outlet_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bread_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outlet_entry_id');
        });
    }
};
