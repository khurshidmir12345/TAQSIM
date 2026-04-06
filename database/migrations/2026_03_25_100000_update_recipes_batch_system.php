<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot table: one recipe can apply to multiple bread types
        Schema::create('recipe_bread_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignUuid('bread_category_id')->constrained('bread_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['recipe_id', 'bread_category_id']);
        });

        // Remove old single FK, flour field, notes from recipes
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropForeign(['bread_category_id']);
            $table->dropColumn(['bread_category_id', 'flour_amount_kg', 'notes']);
        });

        // Productions: add batch_count, make flour_used_kg nullable (calculated)
        Schema::table('productions', function (Blueprint $table) {
            $table->decimal('batch_count', 10, 2)->default(1)->after('date');
            $table->decimal('flour_used_kg', 10, 2)->nullable()->change();
            $table->text('notes')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn('batch_count');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignUuid('bread_category_id')->nullable()->constrained('bread_categories')->cascadeOnDelete();
            $table->decimal('flour_amount_kg', 10, 2)->default(0);
            $table->text('notes')->nullable();
        });

        Schema::dropIfExists('recipe_bread_categories');
    }
};
