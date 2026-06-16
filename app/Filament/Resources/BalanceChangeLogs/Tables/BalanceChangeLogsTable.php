<?php

namespace App\Filament\Resources\BalanceChangeLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BalanceChangeLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('arenaSetting.name')
                    ->label('Настройка')
                    ->placeholder('Удалена')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->placeholder('Система')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('changed_fields')
                    ->label('Поля')
                    ->formatStateUsing(fn (mixed $state): string => self::formatList($state))
                    ->wrap(),
                TextColumn::make('before_values')
                    ->label('Было')
                    ->formatStateUsing(fn (mixed $state): string => self::formatValues($state))
                    ->wrap()
                    ->limit(180),
                TextColumn::make('after_values')
                    ->label('Стало')
                    ->formatStateUsing(fn (mixed $state): string => self::formatValues($state))
                    ->wrap()
                    ->limit(180),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function formatList(mixed $state): string
    {
        if (! is_array($state)) {
            return (string) $state;
        }

        return collect($state)->implode(', ');
    }

    private static function formatValues(mixed $state): string
    {
        if (! is_array($state)) {
            return (string) $state;
        }

        return collect($state)
            ->map(fn (mixed $value, string|int $key): string => $key.': '.self::stringValue($value))
            ->implode('; ');
    }

    private static function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }
}
