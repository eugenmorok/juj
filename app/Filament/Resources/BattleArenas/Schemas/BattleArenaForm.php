<?php

namespace App\Filament\Resources\BattleArenas\Schemas;

use App\Models\BattleArena;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BattleArenaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Локация')
                ->schema([
                    TextInput::make('name')
                        ->label('Название')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('code')
                        ->label('Код')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    FileUpload::make('background_image')
                        ->label('Фон арены')
                        ->disk('public')
                        ->directory('media/arena/backgrounds')
                        ->visibility('public')
                        ->image()
                        ->imageEditor()
                        ->maxSize(12288)
                        ->required(),
                    TextInput::make('sort_order')
                        ->label('Порядок')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Участвует в случайном выборе')
                        ->default(true),
                    Textarea::make('description')
                        ->label('Описание эффекта')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Эффект SPECIAL для обоих участников')
                ->description('Допустимый диапазон каждого эффекта: от -10 до +10. Эффект одинаково применяется к обеим сущностям.')
                ->schema(
                    collect(BattleArena::specialOptions())
                        ->map(fn (string $label, string $attribute): TextInput => TextInput::make("special_effects.{$attribute}")
                            ->label($label)
                            ->numeric()
                            ->integer()
                            ->minValue(-10)
                            ->maxValue(10)
                            ->default(0)
                            ->required())
                        ->values()
                        ->all(),
                )
                ->columns(4),
        ]);
    }
}
