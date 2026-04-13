<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('type', 50)->unique()->index();
            $table->string('url', 2048);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default entries
        DB::table('system_links')->insert([
            [
                'id'         => \Illuminate\Support\Str::uuid(),
                'name'       => 'Foydalanish shartlari',
                'type'       => 'terms',
                'url'        => 'https://taqseem.uz/terms',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => \Illuminate\Support\Str::uuid(),
                'name'       => 'Maxfiylik siyosati',
                'type'       => 'privacy',
                'url'        => 'https://taqseem.uz/privacy',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_links');
    }
};
