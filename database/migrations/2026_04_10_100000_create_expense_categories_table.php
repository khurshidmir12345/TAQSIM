<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 64);
            $table->timestamps();

            $table->unique(['shop_id', 'user_id', 'name']);
            $table->index(['shop_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
