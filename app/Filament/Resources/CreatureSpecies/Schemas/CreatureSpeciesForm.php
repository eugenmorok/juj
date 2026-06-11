<?php

namespace App\Filament\Resources\CreatureSpecies\Schemas;

use App\Models\CreatureSpecies;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreatureSpeciesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        Select::make('creature_type_id')
                            ->label('Тип')
                            ->relationship('type', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label('Код')
                            ->required()
                            ->alphaDash()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('rarity')
                            ->label('Ранг')
                            ->options(CreatureSpecies::RARITIES)
                            ->default('common')
                            ->required(),
                        Toggle::make('is_starter_available')
                            ->label('Доступен при создании')
                            ->default(true),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        Textarea::make('description')
                            ->label('Описание')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Базовые SPECIAL')
                    ->schema([
                        TextInput::make('base_strength')
                            ->label('Strength')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),
                        TextInput::make('base_perception')
                            ->label('Perception')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),
                        TextInput::make('base_endurance')
                            ->label('Endurance')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),
                        TextInput::make('base_charisma')
                            ->label('Charisma')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),
                        TextInput::make('base_intelligence')
                            ->label('Intelligence')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),
                        TextInput::make('base_agility')
                            ->label('Agility')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),
                        TextInput::make('base_luck')
                            ->label('Luck')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),
                    ])
                    ->columns(4),
            ]);
    }
}
