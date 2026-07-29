<?php

namespace App\Support;

use Illuminate\Database\QueryException;

final class DatabaseIntegrityException
{
    /**
     * Faqat duplicate unique key (MySQL 1062 / SQLite UNIQUE) ni aniqlaydi.
     * Umumiy SQLSTATE 23000 yoki boshqa integrity xatolarini qoplamaydi.
     */
    public static function isDuplicateKeyViolation(QueryException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;

        if ($driverCode === 1062) {
            return true;
        }

        $message = $exception->getMessage();

        if (str_contains($message, 'Duplicate entry')) {
            return true;
        }

        return str_contains($message, 'UNIQUE constraint failed');
    }
}
