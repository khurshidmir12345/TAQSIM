<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ilova ichidagi bildirishnomalar ro'yxati (Profil → Bildirishnomalar).
 *
 * Matn yaratilgan paytda foydalanuvchi tilida hal qilinadi va shu holicha
 * saqlanadi — tarixni qayta tarjima qilish shart emas.
 *
 * Muhim: yozuv push sozlamasidan qat'i nazar YARATILADI. Sozlama faqat push
 * yuborilishini boshqaradi, ya'ni foydalanuvchi push'ni o'chirsa ham xabarni
 * ilova ichida ko'radi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            /** daily_greeting | order_reminder | employee_added | system | admin */
            $table->string('category', 32);

            $table->string('title');
            $table->text('body');

            /** Bosilganda qayerga o'tish kerakligi va boshqa qo'shimcha ma'lumot. */
            $table->json('data')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Ro'yxat: eng yangisi tepada; o'qilmaganlar sonini sanash.
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
