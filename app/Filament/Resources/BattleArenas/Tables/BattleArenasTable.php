<?php

namespace App\Filament\Resources\BattleArenas\Tables;

use App\Models\BattleArena;
use App\Support\MediaUrl;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BattleArenasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('background_image')
                    ->label('Фон')
                    ->getStateUsing(fn (BattleArena $record): ?string => MediaUrl::resolve($record->background_image))
                    ->square(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable(),
                TextColumn::make('special_effects')
                    ->label('SPECIAL')
                    ->formatStateUsing(fn (?array $state): string => self::formatEffects($state))
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Активность'),
            ])
            ->defaultSort('sort_order')
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

    private static function formatEffects(?array $effects): string
    {
        $labels = array_keys(BattleArena::specialOptions());
        $short = ['strength' => 'S', 'perception' => 'P', 'endurance' => 'E', 'charisma' => 'C', 'intelligence' => 'I', 'agility' => 'A', 'luck' => 'L'];

        return collect($labels)
            ->filter(fn (string $key): bool => (int) ($effects[$key] ?? 0) !== 0)
            ->map(fn (string $key): string => $short[$key].sprintf('%+d', (int) $effects[$key]))
            ->implode(' · ') ?: 'Без эффекта';
    }
}
