<?php

namespace App\Services\Admin;

use App\Models\SystemBot;
use App\Models\User;
use App\Services\TelegramBotService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\Facades\Log;

/**
 * Admin paneldan bitta foydalanuvchiga Telegram bot orqali xabar.
 *
 * `telegram:broadcast` bilan bir xil bot ("register") ishlatiladi —
 * foydalanuvchi aynan shu bot bilan ulangan.
 */
class DirectMessage
{
    public function __construct(
        private readonly TelegramBotService $telegram,
    ) {}

    public static function botToken(): ?string
    {
        return SystemBot::query()
            ->where('type', 'register')
            ->where('is_active', true)
            ->latest()
            ->value('token');
    }

    /** @return string|null xato matni; `null` — yuborildi */
    public function send(User $user, string $text): ?string
    {
        if ($user->telegram_chat_id === null) {
            return 'Foydalanuvchi Telegram ulamagan.';
        }

        $token = static::botToken();
        if ($token === null) {
            return 'Faol register bot topilmadi.';
        }

        // Bot HTML rejimida yuboradi — admin matnidagi <, & belgilari buzmasin.
        $ok = $this->telegram->sendMessage($token, (int) $user->telegram_chat_id, e(trim($text)));

        Log::info('[admin-message] ' . ($ok ? 'Yuborildi' : 'Yuborilmadi'), [
            'user_id' => $user->id,
            'admin_id' => auth()->id(),
        ]);

        return $ok ? null : 'Telegram xabarni qabul qilmadi (foydalanuvchi botni bloklagan bo\'lishi mumkin).';
    }

    /**
     * "Telegram xabar" tugmasi — user jadvali, user va do'kon sahifalari uchun.
     *
     * @param  \Closure(mixed): ?User  $resolveUser  yozuvdan qabul qiluvchini topadi
     */
    public static function configure(Action|TableAction $action, \Closure $resolveUser): Action|TableAction
    {
        return $action
            ->label('Telegram xabar')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->modalHeading(fn ($record) => 'Telegram xabar: ' . ($resolveUser($record)?->name ?? '—'))
            ->modalSubmitActionLabel('Yuborish')
            ->visible(fn ($record) => $resolveUser($record)?->telegram_chat_id !== null)
            ->form([
                Forms\Components\Textarea::make('text')
                    ->label('Xabar matni')
                    ->helperText('Foydalanuvchiga TAQSEEM boti orqali boradi.')
                    ->rows(6)
                    ->maxLength(4000)
                    ->required(),
            ])
            ->action(function ($record, array $data) use ($resolveUser) {
                $error = app(static::class)->send($resolveUser($record), $data['text']);

                $error === null
                    ? Notification::make()->title('Xabar yuborildi')->success()->send()
                    : Notification::make()->title('Yuborilmadi')->body($error)->danger()->send();
            });
    }
}
