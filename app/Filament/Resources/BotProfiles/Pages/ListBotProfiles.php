<?php

namespace App\Filament\Resources\BotProfiles\Pages;

use App\Filament\Resources\BotProfiles\BotProfileResource;
use App\Models\BotProfile;
use App\Services\BotGenerationService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListBotProfiles extends ListRecords
{
    protected static string $resource = BotProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateBatch')
                ->label('Сгенерировать пачку')
                ->schema([
                    TextInput::make('count')
                        ->label('Количество')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(5)
                        ->required(),
                    Select::make('style')
                        ->label('Стиль')
                        ->options(BotProfile::STYLES)
                        ->default('balanced')
                        ->required(),
                    TextInput::make('min_level')
                        ->label('Мин. уровень')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    TextInput::make('max_level')
                        ->label('Макс. уровень')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->default(3)
                        ->required(),
                ])
                ->action(fn (array $data): mixed => app(BotGenerationService::class)->generateBatch(
                    count: (int) $data['count'],
                    style: $data['style'],
                    minLevel: (int) $data['min_level'],
                    maxLevel: (int) $data['max_level'],
                    withCreature: true,
                    withEquipment: true,
                ))
                ->successNotificationTitle('Пачка ботов сгенерирована'),
            CreateAction::make(),
        ];
    }
}
