<?php

namespace App\Filament\Pages;

use App\Services\DatabaseBackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackup extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Tizim';

    protected static ?string $navigationLabel = 'Zaxira nusxa';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.database-backup';

    public function getTitle(): string
    {
        return "Ma'lumotlar bazasi zaxirasi";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadSql')
                ->label('SQL yuklab olish')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Bazani SQL sifatida yuklab olish')
                ->modalDescription("To'g'ridan-to'g'ri MySQL/MariaDB ga import bo'ladigan .sql fayl yaratiladi.")
                ->modalSubmitActionLabel('Yuklab olish')
                ->action(fn (): ?BinaryFileResponse => $this->download('sql')),

            Action::make('downloadZip')
                ->label('ZIP yuklab olish')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Bazani ZIP sifatida yuklab olish')
                ->modalDescription("Siqilgan .zip fayl yaratiladi (ichida .sql). Import uchun avval oching.")
                ->modalSubmitActionLabel('Yuklab olish')
                ->action(fn (): ?BinaryFileResponse => $this->download('zip')),
        ];
    }

    public function download(string $format): ?BinaryFileResponse
    {
        try {
            $sqlPath = app(DatabaseBackupService::class)->createSqlDump();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Zaxira olishda xatolik')
                ->body($e->getMessage())
                ->send();

            return null;
        }

        if ($format === 'zip') {
            return $this->downloadAsZip($sqlPath);
        }

        Notification::make()
            ->success()
            ->title('Zaxira tayyor')
            ->body('SQL fayl yuklab olinmoqda.')
            ->send();

        return response()
            ->download($sqlPath, basename($sqlPath), ['Content-Type' => 'application/sql'])
            ->deleteFileAfterSend(true);
    }

    private function downloadAsZip(string $sqlPath): ?BinaryFileResponse
    {
        $zipPath = $sqlPath.'.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($sqlPath);

            Notification::make()
                ->danger()
                ->title('ZIP yaratib bo\'lmadi')
                ->send();

            return null;
        }

        $zip->addFile($sqlPath, basename($sqlPath));
        $zip->close();

        @unlink($sqlPath);

        Notification::make()
            ->success()
            ->title('Zaxira tayyor')
            ->body('ZIP fayl yuklab olinmoqda.')
            ->send();

        return response()
            ->download($zipPath, basename($zipPath), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    protected function getViewData(): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        return [
            'connectionName' => $connection,
            'databaseName' => $config['database'] ?? '—',
            'databaseHost' => $config['host'] ?? '—',
            'databasePort' => $config['port'] ?? '—',
        ];
    }
}
