<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\CreatureSpecies;
use App\Models\CreatureType;
use App\Models\EquipmentSlot;
use App\Models\Item;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemForm
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
                            ->helperText('URL, путь из public или короткий текстовый знак.')
                            ->maxLength(255),
                        Select::make('item_type')
                            ->label('Тип предмета')
                            ->options(Item::TYPES)
                            ->default('equipment')
                            ->required(),
                        Select::make('rarity')
                            ->label('Редкость')
                            ->options(Item::RARITIES)
                            ->default('common')
                            ->required(),
                        TextInput::make('price')
                            ->label('Цена')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('required_level')
                            ->label('Мин. уровень')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Toggle::make('is_unique')
                            ->label('Уникальный')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        Textarea::make('description')
                            ->label('Описание')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Мультимедиа эффекта')
                    ->schema([
                        FileUpload::make('effect_image')
                            ->label('Изображение эффекта')
                            ->helperText('Прозрачный WebP/PNG для вспышки, лечения или усиления.')
                            ->disk('public')
                            ->directory('media/items/effects')
                            ->visibility('public')
                            ->image()
                            ->maxSize(4096),
                        FileUpload::make('effect_sound')
                            ->label('Звук эффекта')
                            ->helperText('OGG, MP3 или WAV. Звук будет подключен на следующем мультимедиа-этапе.')
                            ->disk('public')
                            ->directory('media/items/sounds')
                            ->visibility('public')
                            ->acceptedFileTypes(['audio/ogg', 'audio/mpeg', 'audio/wav'])
                            ->maxSize(8192),
                    ])
                    ->columns(2),
                Section::make('Экипировка и ограничения')
                    ->schema([
                        Select::make('slot_key')
                            ->label('Основной слот')
                            ->options(fn (): array => self::slotOptions())
                            ->searchable()
                            ->nullable(),
                        Select::make('slots_required')
                            ->label('Занимаемые слоты')
                            ->options(fn (): array => self::slotOptions())
                            ->multiple()
                            ->searchable()
                            ->dehydrateStateUsing(fn (?array $state): ?array => self::nullableValues($state)),
                        Select::make('allowed_types')
                            ->label('Разрешенные типы')
                            ->options(fn (): array => CreatureType::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->dehydrateStateUsing(fn (?array $state): ?array => self::nullableIntegerValues($state)),
                        Select::make('allowed_species')
                            ->label('Разрешенные виды')
                            ->options(fn (): array => CreatureSpecies::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->dehydrateStateUsing(fn (?array $state): ?array => self::nullableIntegerValues($state)),
                    ])
                    ->columns(2),
                Section::make('Эффекты')
                    ->schema([
                        KeyValue::make('bonuses')
                            ->label('Бонусы')
                            ->keyLabel('Параметр')
                            ->valueLabel('Значение')
                            ->helperText('Боевые ключи: damage/attack добавляют Урон, defense/armor добавляют Защиту. SPECIAL-ключи по-прежнему работают.')
                            ->columnSpanFull(),
                        Select::make('duration_type')
                            ->label('Длительность')
                            ->options(Item::DURATIONS)
                            ->default('permanent')
                            ->required(),
                        TextInput::make('uses_count')
                            ->label('Кол-во использований')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function slotOptions(): array
    {
        return EquipmentSlot::query()
            ->active()
            ->orderBy('sort_order')
            ->pluck('name', 'code')
            ->all();
    }

    /**
     * @param  list<mixed>|null  $state
     * @return list<string>|null
     */
    private static function nullableValues(?array $state): ?array
    {
        $values = collect($state ?? [])
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->values()
            ->all();

        return $values === [] ? null : $values;
    }

    /**
     * @param  list<mixed>|null  $state
     * @return list<int>|null
     */
    private static function nullableIntegerValues(?array $state): ?array
    {
        $values = collect($state ?? [])
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        return $values === [] ? null : $values;
    }
}
