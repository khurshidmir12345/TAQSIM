<?php

namespace App\Filament\Pages;

use App\Jobs\SendBulkNotification;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
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

                Select::make('user_ids')
                    ->label('Foydalanuvchilar')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (User $u): array => [$u->id => "{$u->name} — {$u->phone}"])
                        ->all())
                    ->getOptionLabelsUsing(fn (array $values): array => User::query()
                        ->whereIn('id', $values)
                        ->get()
                        ->mapWithKeys(fn (User $u): array => [$u->id => "{$u->name} — {$u->phone}"])
                        ->all())
                    ->visible(fn (callable $get): bool => $get('target') === 'user')
                    ->required(fn (callable $get): bool => $get('target') === 'user')
                    ->helperText('Ism yoki telefon bo\'yicha qidiring.'),

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

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Yuborish')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading('Bildirishnoma yuborilsinmi?')
                ->modalDescription(fn (): string => $this->confirmationText())
                ->modalSubmitActionLabel('Ha, yuborilsin')
                ->action('send'),
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

    private function confirmationText(): string
    {
        $state = $this->form->getRawState();

        if (($state['target'] ?? 'all') === 'all') {
            $count = User::query()->whereNull('blocked_at')->count();

            return "Bu xabar {$count} ta foydalanuvchiga boradi. Bekor qilib bo'lmaydi.";
        }

        $count = count($state['user_ids'] ?? []);

        return "Bu xabar {$count} ta foydalanuvchiga boradi.";
    }
}
