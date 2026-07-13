<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Joriy ma'lumotlar bazasidan to'liq zaxira (.sql dump) yaratadi.
 *
 * Avval `mysqldump` binary orqali urinadi (eng to'liq nusxa: triggers,
 * routines, events). Agar binary topilmasa yoki xato bersa — PHP orqali
 * (PDO) zaxira olishga o'tadi. Har ikkala natija ham MySQL/MariaDB ga
 * to'g'ridan-to'g'ri import bo'ladi.
 */
class DatabaseBackupService
{
    /**
     * Zaxira faylini yaratadi va uning absolute yo'lini qaytaradi.
     */
    public function createSqlDump(): string
    {
        $directory = storage_path('app/'.config('backup.directory', 'backups'));

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = sprintf(
            '%s_%s.sql',
            $this->dbConfig('database'),
            now()->format('Y-m-d_His'),
        );

        $path = $directory.DIRECTORY_SEPARATOR.$fileName;

        if (! $this->dumpWithBinary($path)) {
            $this->dumpWithPhp($path);
        }

        return $path;
    }

    /**
     * Joriy ulanish (connection) konfiguratsiyasidan qiymat oladi.
     */
    private function dbConfig(string $key, mixed $default = null): mixed
    {
        $connection = config('database.default');

        return config("database.connections.{$connection}.{$key}", $default);
    }

    // ─── 1) mysqldump binary orqali ─────────────────────────────────────────

    private function dumpWithBinary(string $path): bool
    {
        // Parolni CLI da ochiq ko'rsatmaslik uchun vaqtinchalik defaults-file.
        $defaultsFile = tempnam(sys_get_temp_dir(), 'mysqldump_');

        file_put_contents($defaultsFile, sprintf(
            "[client]\nhost=%s\nport=%s\nuser=%s\npassword=\"%s\"\n",
            $this->dbConfig('host', '127.0.0.1'),
            $this->dbConfig('port', '3306'),
            $this->dbConfig('username'),
            str_replace('"', '\\"', (string) $this->dbConfig('password')),
        ));
        @chmod($defaultsFile, 0600);

        $handle = null;

        try {
            $process = new Process([
                config('backup.mysqldump_path', 'mysqldump'),
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                (string) $this->dbConfig('database'),
            ]);
            $process->setTimeout((float) config('backup.timeout', 600));

            $handle = fopen($path, 'w');

            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });

            if ($handle) {
                fclose($handle);
                $handle = null;
            }

            if (! $process->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
                @unlink($path);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($path);

            return false;
        } finally {
            @unlink($defaultsFile);
        }
    }

    // ─── 2) PHP (PDO) orqali fallback ───────────────────────────────────────

    private function dumpWithPhp(string $path): void
    {
        $handle = fopen($path, 'w');
        $database = (string) $this->dbConfig('database');

        fwrite($handle, "-- Taqseem database backup (PHP fallback)\n");
        fwrite($handle, "-- Database: {$database}\n");
        fwrite($handle, '-- Generated: '.now()->toDateTimeString()."\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach ($this->tableNames() as $table) {
            $this->writeTableSchema($handle, $table);
            $this->writeTableData($handle, $table);
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);
    }

    /**
     * @return array<int, string>
     */
    private function tableNames(): array
    {
        return array_map(
            static fn ($row): string => array_values((array) $row)[0],
            DB::select('SHOW TABLES'),
        );
    }

    /**
     * @param  resource  $handle
     */
    private function writeTableSchema($handle, string $table): void
    {
        $row = (array) DB::select('SHOW CREATE TABLE `'.$table.'`')[0];
        $create = $row['Create Table'] ?? $row['Create View'] ?? null;

        if ($create === null) {
            return;
        }

        fwrite($handle, "\n-- ----------------------------\n");
        fwrite($handle, "-- Structure for `{$table}`\n");
        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, 'DROP TABLE IF EXISTS `'.$table."`;\n");
        fwrite($handle, $create.";\n\n");
    }

    /**
     * @param  resource  $handle
     */
    private function writeTableData($handle, string $table): void
    {
        $pdo = DB::getPdo();
        $wrote = false;

        foreach (DB::table($table)->cursor() as $record) {
            $record = (array) $record;

            $columns = implode(', ', array_map(
                static fn (string $col): string => '`'.$col.'`',
                array_keys($record),
            ));

            $values = implode(', ', array_map(
                static fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                array_values($record),
            ));

            fwrite($handle, 'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.$values.");\n");
            $wrote = true;
        }

        if ($wrote) {
            fwrite($handle, "\n");
        }
    }
}
