<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bread_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->string('image_url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'is_active']);
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 16)->default('kg');
            $table->boolean('is_flour')->default(false);
            $table->decimal('price_per_unit', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'is_active']);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('bread_category_id')->constrained('bread_categories')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('flour_amount_kg', 10, 2);
            $table->unsignedInteger('output_quantity');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'is_active']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_id']);
        });

        Schema::create('productions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('recipe_id')->constrained('recipes');
            $table->foreignUuid('bread_category_id')->constrained('bread_categories');
            $table->date('date');
            $table->decimal('flour_used_kg', 10, 2);
            $table->unsignedInteger('bread_produced');
            $table->decimal('ingredient_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['shop_id', 'date']);
        });

        Schema::create('bread_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('bread_category_id')->constrained('bread_categories');
            $table->date('date');
            $table->unsignedInteger('quantity');
            $table->decimal('price_per_unit', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->string('reason')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['shop_id', 'date']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('category', 64);
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['shop_id', 'date']);
            $table->index(['shop_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('bread_returns');
        Schema::dropIfExists('productions');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('bread_categories');
    }
};
