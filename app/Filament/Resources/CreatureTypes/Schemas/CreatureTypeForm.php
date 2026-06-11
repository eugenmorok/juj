<?php

namespace App\Filament\Resources\CreatureTypes\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreatureTypeForm
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
                        TextInput::make('icon')
                            ->label('Иконка')
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        Textarea::make('description')
                            ->label('Описание')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Баланс')
                    ->schema([
                        KeyValue::make('type_bonus')
                            ->label('Бонусы типа')
                            ->keyLabel('Параметр')
                            ->valueLabel('Значение'),
                        KeyValue::make('type_weakness')
                            ->label('Слабости типа')
                            ->keyLabel('Параметр')
                            ->valueLabel('Значение'),
                    ])
                    ->columns(2),
            ]);
    }
}
