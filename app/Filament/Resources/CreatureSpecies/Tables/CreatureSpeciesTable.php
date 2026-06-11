<?php

namespace App\Filament\Resources\CreatureSpecies\Tables;

use App\Models\CreatureSpecies;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CreatureSpeciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type.name')
                    ->label('Тип')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rarity')
                    ->label('Ранг')
                    ->formatStateUsing(fn (string $state): string => CreatureSpecies::RARITIES[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('base_strength')
                    ->label('S')
                    ->sortable(),
                TextColumn::make('base_perception')
                    ->label('P')
                    ->sortable(),
                TextColumn::make('base_endurance')
                    ->label('E')
                    ->sortable(),
                TextColumn::make('base_charisma')
                    ->label('C')
                    ->sortable(),
                TextColumn::make('base_intelligence')
                    ->label('I')
                    ->sortable(),
                TextColumn::make('base_agility')
                    ->label('A')
                    ->sortable(),
                TextColumn::make('base_luck')
                    ->label('L')
                    ->sortable(),
                IconColumn::make('is_starter_available')
                    ->label('Стартовый')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('creature_type_id')
                    ->label('Тип')
                    ->relationship('type', 'name')
                    ->preload(),
                SelectFilter::make('rarity')
                    ->label('Ранг')
                    ->options(CreatureSpecies::RARITIES),
                TernaryFilter::make('is_starter_available')
                    ->label('Доступен при создании'),
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
