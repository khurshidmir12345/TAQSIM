<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * .env dagi FILAMENT_ADMIN_EMAIL/PASSWORD asosida yagona admin
     * foydalanuvchisini yaratadi. Mavjud admin paroli yoki boshqa
     * maydonlar qayta yozilmaydi (production-safe).
     */
    public function run(): void
    {
        $email = $this->normalizeEmail(config('admin.email'));
        $password = $this->normalizePassword(config('admin.password'));

        if ($email === null || $password === null) {
            $this->command?->warn(
                'FILAMENT_ADMIN_EMAIL yoki FILAMENT_ADMIN_PASSWORD .env da yo\'q yoki yaroqsiz — admin yaratilmadi.'
            );

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $this->normalizeName(config('admin.name')),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_accepted_policy' => true,
            ],
        );

        if (! $user->wasRecentlyCreated) {
            $this->command?->info("Admin allaqachon mavjud: {$email} — parol o'zgartirilmadi.");

            return;
        }

        $this->command?->info("Admin tayyor: {$email}");
    }

    private function normalizeEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    private function normalizePassword(mixed $password): ?string
    {
        if (! is_string($password)) {
            return null;
        }

        $password = trim($password);

        return $password !== '' ? $password : null;
    }

    private function normalizeName(mixed $name): string
    {
        if (! is_string($name)) {
            return 'Administrator';
        }

        $name = trim($name);

        return $name !== '' ? $name : 'Administrator';
    }
}
