<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_shops', function (Blueprint $table) {
            // Xodim (seller) uchun granular ruxsatlar. null/[] — hech narsa.
            $table->json('permissions')->nullable()->after('user_type');
            // 'active' | 'past_due' — pulli o'rin to'lovi qaytsa past_due bo'ladi.
            $table->string('seat_status', 16)->default('active')->after('permissions');
            // Bepul limitdan oshganda ochilgan pulli o'rin.
            $table->boolean('is_paid_seat')->default(false)->after('seat_status');
            // Pulli o'rin keyingi to'lov sanasi.
            $table->timestamp('seat_ends_at')->nullable()->after('is_paid_seat');
            // Kim taklif qilgan (owner).
            $table->foreignUuid('invited_by')->nullable()->after('seat_ends_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn(['permissions', 'seat_status', 'is_paid_seat', 'seat_ends_at']);
        });
    }
};
