<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->foreignUuid('business_type_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('business_types')
                  ->nullOnDelete();

            $table->decimal('latitude', 10, 8)->nullable()->after('address');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['business_type_id']);
            $table->dropColumn(['business_type_id', 'latitude', 'longitude']);
        });
    }
};
