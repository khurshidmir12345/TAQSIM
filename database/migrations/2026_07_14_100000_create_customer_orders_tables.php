<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('note')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'name']);
        });

        Schema::create('customer_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->date('delivery_date');
            $table->time('delivery_time')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->text('note')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['shop_id', 'delivery_date']);
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'customer_id']);
        });

        Schema::create('customer_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignUuid('bread_category_id')->constrained('bread_categories');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::create('customer_order_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->dateTime('paid_at');
            $table->text('note')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['shop_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_payments');
        Schema::dropIfExists('customer_order_items');
        Schema::dropIfExists('customer_orders');
        Schema::dropIfExists('customers');
    }
};
