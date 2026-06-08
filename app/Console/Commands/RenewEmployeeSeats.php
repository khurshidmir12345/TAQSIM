<?php

namespace App\Console\Commands;

use App\Services\EmployeeService;
use Illuminate\Console\Command;

class RenewEmployeeSeats extends Command
{
    protected $signature = 'employees:renew-seats';

    protected $description = 'Muddati kelgan pulli xodim o\'rinlarini owner balansidan yangilaydi';

    public function handle(EmployeeService $employees): int
    {
        $result = $employees->renewDueSeats();

        $this->info("Yangilandi: {$result['renewed']}, to'xtatildi (balans yetmadi): {$result['suspended']}");

        return self::SUCCESS;
    }
}
