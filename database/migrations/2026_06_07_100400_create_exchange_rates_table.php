<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valyuta kurslari. Billing uchun asosiy: USD → UZS.
     * Manba: CBU (Markaziy bank) API yoki admin qo'lda (manual override).
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('base_code', 8);  // USD
            $table->string('quote_code', 8); // UZS
            $table->decimal('rate', 15, 4);
            $table->string('source', 16)->default('manual'); // manual | cbu
            $table->boolean('is_active')->default(true);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['base_code', 'quote_code']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
