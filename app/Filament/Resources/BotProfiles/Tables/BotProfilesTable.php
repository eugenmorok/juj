<?php

namespace App\Filament\Resources\BotProfiles\Tables;

use App\Models\BotProfile;
use App\Services\BotGenerationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BotProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('style')
                    ->label('Стиль')
                    ->formatStateUsing(fn (string $state): string => BotProfile::STYLES[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('generated_creatures_count')
                    ->label('Сущностей')
                    ->sortable(),
                TextColumn::make('spawn_chance')
                    ->label('Появление')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('min_level')
                    ->label('Мин.')
                    ->sortable(),
                TextColumn::make('max_level')
                    ->label('Макс.')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('style')
                    ->label('Стиль')
                    ->options(BotProfile::STYLES),
                TernaryFilter::make('is_active')
                    ->label('Активность'),
            ])
            ->defaultSort('display_name')
            ->recordActions([
                Action::make('generateCreature')
                    ->label('Сущность')
                    ->action(fn (BotProfile $record): mixed => app(BotGenerationService::class)->generateCreature($record, withEquipment: true))
                    ->successNotificationTitle('Сущность и экипировка сгенерированы'),
                Action::make('generateEquipment')
                    ->label('Экипировка')
                    ->action(fn (BotProfile $record): int => app(BotGenerationService::class)->generateEquipmentForBot($record))
                    ->successNotificationTitle('Экипировка сгенерирована'),
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
