<?php

namespace App\Filament\Resources\Skills\Tables;

use App\Models\Skill;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SkillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('skill_type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state): string => Skill::TYPES[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('Цена')
                    ->sortable(),
                TextColumn::make('required_level')
                    ->label('Ур.')
                    ->sortable(),
                TextColumn::make('requiredType.name')
                    ->label('Тип сущности')
                    ->placeholder('Любой')
                    ->sortable(),
                TextColumn::make('requiredSpecies.name')
                    ->label('Вид')
                    ->placeholder('Любой')
                    ->sortable(),
                IconColumn::make('is_starter_available')
                    ->label('Стартовый')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('skill_type')
                    ->label('Тип навыка')
                    ->options(Skill::TYPES),
                TernaryFilter::make('is_starter_available')
                    ->label('Можно купить при создании'),
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
