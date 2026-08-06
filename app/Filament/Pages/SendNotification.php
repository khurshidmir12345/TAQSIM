<?php

namespace App\Filament\Pages;

use App\Jobs\SendBulkNotification;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SendNotification extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Tizim';

    protected static ?string $navigationLabel = 'Bildirishnoma yuborish';

    protected static ?int $navigationSort = 80;

    protected static string $view = 'filament.pages.send-notification';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['target' => 'all']);
    }

    public function getTitle(): string
    {
        return 'Bildirishnoma yuborish';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Radio::make('target')
                    ->label('Kimga')
                    ->options([
                        'all' => 'Barcha foydalanuvchilarga',
                        'user' => 'Tanlangan foydalanuvchiga',
                    ])
                    ->default('all')
                    ->live()
                    ->required(),

                // Ro'yxat sifatida — hamma darhol ko'rinadi, qidiruv va
                // "hammasini belgilash" tugmasi bor.
                CheckboxList::make('user_ids')
                    ->label('Foydalanuvchilar')
                    ->options(fn (): array => self::userOptions())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->gridDirection('row')
                    ->visible(fn (Get $get): bool => $get('target') === 'user')
                    ->required(fn (Get $get): bool => $get('target') === 'user')
                    ->helperText('Bir nechtasini belgilashingiz mumkin. '
                        .'Qidiruvdan ism yoki telefon bo\'yicha topasiz.'),

                TextInput::make('title')
                    ->label('Sarlavha')
                    ->required()
                    ->maxLength(120),

                Textarea::make('body')
                    ->label('Matn')
                    ->required()
                    ->rows(4)
                    ->maxLength(1000),
            ])
            ->statePath('data');
    }

    /**
     * Yuborish tugmasi sahifa sarlavhasida turadi — Filament uni o'zi
     * chizadi va tasdiqlash oynasini ham o'zi boshqaradi.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Yuborish')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading('Bildirishnoma yuborilsinmi?')
                ->modalDescription(fn (): string => $this->confirmationText())
                ->modalSubmitActionLabel('Ha, yuborilsin')
                ->action(fn () => $this->send()),
        ];
    }

    public function send(): void
    {
        $data = $this->form->getState();

        $userIds = $data['target'] === 'user' ? array_values($data['user_ids'] ?? []) : null;

        SendBulkNotification::dispatch(
            $userIds,
            $data['title'],
            $data['body'],
        );

        Notification::make()
            ->success()
            ->title('Navbatga qo\'yildi')
            ->body($userIds === null
                ? 'Barcha foydalanuvchilarga yuborilmoqda.'
                : count($userIds).' ta foydalanuvchiga yuborilmoqda.')
            ->send();

        $this->form->fill(['target' => $data['target']]);
    }

    /**
     * Bloklanmagan foydalanuvchilar: `id => "Ism · telefon"`.
     * Ismi yo'qlar ham ro'yxatda qolsin — telefon bo'yicha tanish mumkin.
     *
     * @return array<string,string>
     */
    private static function userOptions(): array
    {
        return User::query()
            ->whereNull('blocked_at')
            ->orderByRaw('name IS NULL, name')
            ->get(['id', 'name', 'phone'])
            ->mapWithKeys(fn (User $u): array => [
                $u->id => trim(($u->name ?: '—').' · '.$u->phone),
            ])
            ->all();
    }

    private function confirmationText(): string
    {
        $state = is_array($this->data) ? $this->data : [];

        if (($state['target'] ?? 'all') === 'all') {
            $count = User::query()->whereNull('blocked_at')->count();

            return "Bu xabar {$count} ta foydalanuvchiga boradi. Bekor qilib bo'lmaydi.";
        }

        $count = count($state['user_ids'] ?? []);

        return "Bu xabar {$count} ta foydalanuvchiga boradi.";
    }
}
