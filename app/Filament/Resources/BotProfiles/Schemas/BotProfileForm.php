<?php

namespace App\Filament\Resources\BotProfiles\Schemas;

use App\Models\BotProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BotProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Профиль')
                    ->schema([
                        TextInput::make('display_name')
                            ->label('Имя бота')
                            ->required()
                            ->maxLength(255),
                        Select::make('style')
                            ->label('Стиль')
                            ->options(BotProfile::STYLES)
                            ->default('balanced')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Активен в матчмейкинге')
                            ->default(true),
                        TextInput::make('spawn_chance')
                            ->label('Частота появления, %')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(100)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Генерация')
                    ->schema([
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
                        Textarea::make('notes')
                            ->label('Заметки')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
