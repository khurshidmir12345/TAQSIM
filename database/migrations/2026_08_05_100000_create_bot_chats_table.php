<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bitta bot bir nechta guruhda bo'lishi mumkin (kodlar guruhi, savol-javob
 * guruhi va h.k.). `system_bots.chat_id` bitta guruhnigina saqlay olardi.
 *
 * Guruhlar bot qo'shilganda avtomatik yoziladi (`my_chat_member` webhook),
 * `purpose` esa admin panelda tanlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_chats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('system_bot_id')->constrained('system_bots')->cascadeOnDelete();

            // Guruh ID'lari katta manfiy son (-100...) — string sifatida saqlanadi.
            $table->string('chat_id');
            $table->string('title')->nullable();

            /** notify — kod/bildirishnoma guruhi; support — foydalanuvchi savollari. */
            $table->string('purpose')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['system_bot_id', 'chat_id']);
            $table->index(['purpose', 'is_active']);
        });

        // Mavjud `system_bots.chat_id` qiymatlarini ko'chiramiz — hozirgi
        // kod yuborish oqimi uzilmasligi uchun.
        $bots = DB::table('system_bots')->whereNotNull('chat_id')->get();

        foreach ($bots as $bot) {
            if (trim((string) $bot->chat_id) === '') {
                continue;
            }

            DB::table('bot_chats')->insert([
                'id' => (string) Str::uuid(),
                'system_bot_id' => $bot->id,
                'chat_id' => $bot->chat_id,
                'title' => $bot->name,
                'purpose' => 'notify',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_chats');
    }
};
