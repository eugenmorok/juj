<?php

namespace App\Filament\Resources\Items\Tables;

use App\Models\Item;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('icon')
                    ->label('Иконка')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('item_type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state): string => Item::TYPES[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('rarity')
                    ->label('Редкость')
                    ->formatStateUsing(fn (string $state): string => Item::RARITIES[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->sortable(),
                TextColumn::make('required_level')
                    ->label('Ур.')
                    ->sortable(),
                TextColumn::make('slot.name')
                    ->label('Слот')
                    ->placeholder('Без слота')
                    ->sortable(),
                IconColumn::make('is_unique')
                    ->label('Уник.')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('item_type')
                    ->label('Тип предмета')
                    ->options(Item::TYPES),
                SelectFilter::make('rarity')
                    ->label('Редкость')
                    ->options(Item::RARITIES),
                TernaryFilter::make('is_unique')
                    ->label('Уникальность'),
                TernaryFilter::make('is_active')
                    ->label('Активность'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
