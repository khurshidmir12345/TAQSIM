<?php

namespace Database\Seeders;

use App\Models\SystemBot;
use Illuminate\Database\Seeder;

class SystemBotSeeder extends Seeder
{
    public function run(): void
    {
        SystemBot::updateOrCreate(
            ['type' => 'register'],
            [
                'name' => 'TAQSEEM Register Bot',
                'username' => 't_register_bot',
                'token' => '8638073143:AAGMD1coeHP84sss2yK3crq40zud8ZzLVEE',
                'is_active' => true,
            ]
        );
    }
}
