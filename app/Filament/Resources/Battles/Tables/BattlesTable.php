<?php

namespace App\Filament\Resources\Battles\Tables;

use App\Models\Battle;
use App\Models\BattleParticipant;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BattlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['initiator', 'winner', 'participants.creature.user'])
                ->withCount('events'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('participants_summary')
                    ->label('Участники')
                    ->state(fn (Battle $record): string => $record->participants
                        ->map(fn (BattleParticipant $participant): string => $participant->creature?->name ?? 'без сущности')
                        ->implode(' vs '))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('participants.creature', fn (Builder $creatures): Builder => $creatures
                            ->where('name', 'like', "%{$search}%"));
                    })
                    ->wrap(),
                TextColumn::make('mode')
                    ->label('Режим')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Battle::MODE_INTERACTIVE => 'пошаговый',
                        Battle::MODE_INSTANT, null => 'мгновенный',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),
                TextColumn::make('battle_type')
                    ->label('Тип')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Battle::TYPE_RANKED => 'рейтинговый',
                        Battle::TYPE_SIMULATION => 'симуляция',
                        default => (string) $state,
                    })
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        Battle::STATUS_RUNNING => 'warning',
                        Battle::STATUS_FINISHED => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('winner.name')
                    ->label('Победитель')
                    ->placeholder('ничья / в бою')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_draw')
                    ->label('Ничья')
                    ->boolean(),
                TextColumn::make('events_count')
                    ->label('Событий')
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Финиш')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('идет')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('mode')
                    ->label('Режим')
                    ->options([
                        Battle::MODE_INSTANT => 'мгновенный',
                        Battle::MODE_INTERACTIVE => 'пошаговый',
                    ]),
                SelectFilter::make('battle_type')
                    ->label('Тип боя')
                    ->options([
                        Battle::TYPE_RANKED => 'рейтинговый',
                        Battle::TYPE_SIMULATION => 'симуляция',
                    ]),
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        Battle::STATUS_RUNNING => 'идет',
                        Battle::STATUS_FINISHED => 'завершен',
                    ]),
                TernaryFilter::make('is_draw')
                    ->label('Ничья'),
            ])
            ->defaultSort('started_at', 'desc')
            ->recordActions([
                Action::make('openBattle')
                    ->label('Открыть')
                    ->url(fn (Battle $record): string => route('arena.battles.show', $record))
                    ->openUrlInNewTab(),
                Action::make('replay')
                    ->label('Replay')
                    ->url(fn (Battle $record): string => route('arena.battles.replay', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
