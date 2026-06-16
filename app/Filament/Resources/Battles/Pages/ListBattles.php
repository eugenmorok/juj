<?php

namespace App\Filament\Resources\Battles\Pages;

use App\Filament\Resources\Battles\BattleResource;
use App\Models\Battle;
use App\Models\Creature;
use App\Services\BattleEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListBattles extends ListRecords
{
    protected static string $resource = BattleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simulateBattle')
                ->label('Симулировать бой')
                ->schema([
                    Select::make('left_creature_id')
                        ->label('Сущность 1')
                        ->options(fn (): array => self::creatureOptions())
                        ->searchable()
                        ->required(),
                    Select::make('right_creature_id')
                        ->label('Сущность 2')
                        ->options(fn (): array => self::creatureOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('seed')
                        ->label('Seed')
                        ->helperText('Можно оставить пустым для случайного результата.')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(2_147_483_646),
                ])
                ->action(function (array $data): void {
                    if ((int) $data['left_creature_id'] === (int) $data['right_creature_id']) {
                        throw ValidationException::withMessages([
                            'right_creature_id' => 'Для симуляции нужны две разные сущности.',
                        ]);
                    }

                    $battle = app(BattleEngine::class)->run(
                        Creature::query()->findOrFail((int) $data['left_creature_id']),
                        Creature::query()->findOrFail((int) $data['right_creature_id']),
                        isset($data['seed']) && $data['seed'] !== '' ? (int) $data['seed'] : null,
                        Battle::TYPE_SIMULATION,
                        auth()->user(),
                    );

                    Notification::make()
                        ->success()
                        ->title("Симуляция #{$battle->id} создана")
                        ->body('Откройте запись боя или Replay из списка, чтобы посмотреть лог.')
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function creatureOptions(): array
    {
        return Creature::query()
            ->with('user')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Creature $creature): array => [
                $creature->id => "{$creature->name} / {$creature->user?->name} / ур. {$creature->level}",
            ])
            ->all();
    }
}
