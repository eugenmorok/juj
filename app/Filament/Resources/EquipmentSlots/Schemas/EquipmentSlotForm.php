<?php

namespace App\Filament\Resources\EquipmentSlots\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentSlotForm
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
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(100)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        Textarea::make('description')
                            ->label('Описание')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
