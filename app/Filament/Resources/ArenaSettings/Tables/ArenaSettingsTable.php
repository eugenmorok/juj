<?php

namespace App\Filament\Resources\ArenaSettings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ArenaSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                TextColumn::make('win_xp_per_level')
                    ->label('XP победа')
                    ->sortable(),
                TextColumn::make('win_tokens_per_level')
                    ->label('Токены победа')
                    ->sortable(),
                TextColumn::make('matchmaking_level_difference')
                    ->label('Разница уровней')
                    ->sortable(),
                TextColumn::make('matchmaking_power_score_difference')
                    ->label('Разница power')
                    ->sortable(),
                TextColumn::make('daily_battle_limit')
                    ->label('Лимит боев')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Без лимита' : (string) $state)
                    ->sortable(),
                TextColumn::make('updatedBy.name')
                    ->label('Изменил')
                    ->placeholder('Система')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активность'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
