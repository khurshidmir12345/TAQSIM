<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * .env dagi FILAMENT_ADMIN_EMAIL/PASSWORD asosida yagona admin
     * foydalanuvchisini yaratadi yoki yangilaydi. Panelga faqat shu hisob
     * kira oladi (User::canAccessPanel).
     */
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (empty($email) || empty($password)) {
            $this->command?->warn('FILAMENT_ADMIN_EMAIL yoki FILAMENT_ADMIN_PASSWORD .env da yo\'q — admin yaratilmadi.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.name', 'Administrator'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_accepted_policy' => true,
            ],
        );

        $this->command?->info("Admin tayyor: {$email}");
    }
}
