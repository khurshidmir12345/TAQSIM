<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'blocked_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')
                ->nullable()
                ->after('locale')
                ->comment('Admin tomonidan bloklangan sana');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'blocked_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('blocked_at');
        });
    }
};
