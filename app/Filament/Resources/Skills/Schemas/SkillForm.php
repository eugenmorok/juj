<?php

namespace App\Filament\Resources\Skills\Schemas;

use App\Models\Creature;
use App\Models\Skill;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
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
                        Select::make('skill_type')
                            ->label('Тип навыка')
                            ->options(Skill::TYPES)
                            ->default('passive')
                            ->required(),
                        TextInput::make('cost')
                            ->label('Стоимость')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('cooldown_turns')
                            ->label('Кулдаун, раунды')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Toggle::make('is_starter_available')
                            ->label('Можно купить при создании')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        Textarea::make('description')
                            ->label('Описание')
                            ->columnSpanFull(),
                        Textarea::make('effect')
                            ->label('Эффект')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Требования')
                    ->schema([
                        TextInput::make('required_level')
                            ->label('Уровень')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Select::make('required_creature_type_id')
                            ->label('Тип сущности')
                            ->relationship('requiredType', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('required_creature_species_id')
                            ->label('Вид сущности')
                            ->relationship('requiredSpecies', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('required_strength')
                            ->label('Strength')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Creature::STARTER_SPECIAL_CAP)
                            ->default(0)
                            ->required(),
                        TextInput::make('required_perception')
                            ->label('Perception')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Creature::STARTER_SPECIAL_CAP)
                            ->default(0)
                            ->required(),
                        TextInput::make('required_endurance')
                            ->label('Endurance')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Creature::STARTER_SPECIAL_CAP)
                            ->default(0)
                            ->required(),
                        TextInput::make('required_charisma')
                            ->label('Charisma')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Creature::STARTER_SPECIAL_CAP)
                            ->default(0)
                            ->required(),
                        TextInput::make('required_intelligence')
                            ->label('Intelligence')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Creature::STARTER_SPECIAL_CAP)
                            ->default(0)
                            ->required(),
                        TextInput::make('required_agility')
                            ->label('Agility')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Creature::STARTER_SPECIAL_CAP)
                            ->default(0)
                            ->required(),
                        TextInput::make('required_luck')
                            ->label('Luck')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Creature::STARTER_SPECIAL_CAP)
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(4),
            ]);
    }
}
