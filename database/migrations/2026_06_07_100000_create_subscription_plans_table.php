<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Obuna tariflari. Limitlar (max_*) null bo'lsa — cheksiz.
     * Narx USD'da saqlanadi, ilovada UZS'ga (exchange_rates orqali) aylantiriladi.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();

            // Ko'p tilli nom (BusinessType uslubi)
            $table->string('name_uz');
            $table->string('name_uz_cyrl')->nullable();
            $table->string('name_ru')->nullable();
            $table->string('name_kk')->nullable();
            $table->string('name_ky')->nullable();
            $table->string('name_tr')->nullable();

            $table->decimal('price_usd', 10, 2)->default(0);
            $table->string('billing_period', 16)->default('monthly'); // monthly | yearly
            $table->unsignedSmallInteger('duration_days')->default(30);

            // Limitlar (hisob bo'yicha umumiy). null = cheksiz
            $table->integer('max_shops')->nullable();
            $table->integer('max_products')->nullable();
            $table->integer('max_employees')->nullable();

            // Qo'shimcha feature flaglari (ilovada lokalizatsiya qilinadigan kalitlar)
            $table->json('extra_features')->nullable();

            $table->boolean('is_trial')->default(false);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->string('color', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('is_trial');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
