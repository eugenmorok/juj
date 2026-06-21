<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Item;
use App\Models\User;
use App\Services\ShopService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('creatures'))
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->label('Ур.')
                    ->sortable(),
                TextColumn::make('xp')
                    ->label('XP')
                    ->sortable(),
                TextColumn::make('tokens')
                    ->label('Токены')
                    ->sortable(),
                TextColumn::make('creature_creation_points')
                    ->label('Очки создания')
                    ->sortable(),
                TextColumn::make('doctrine_state')
                    ->label('Доктрина')
                    ->state(fn (User $record): string => "св. {$record->doctrine_points} / потрач. {$record->doctrinePointsSpent()}"),
                TextColumn::make('inventory_state')
                    ->label('Инвентарь')
                    ->state(function (User $record): string {
                        $inventory = $record->ensureInventory();

                        return "{$inventory->usedSlots()}/{$inventory->capacity()}";
                    }),
                TextColumn::make('creatures_count')
                    ->label('Сущностей')
                    ->sortable(),
                IconColumn::make('is_bot')
                    ->label('Бот')
                    ->boolean(),
                IconColumn::make('is_admin')
                    ->label('Админ')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_bot')
                    ->label('Бот'),
                TernaryFilter::make('is_admin')
                    ->label('Администратор'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('grantTokens')
                    ->label('Токены')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Сумма')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(1_000_000)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        app(ShopService::class)->grantTokens($record, (int) $data['amount']);

                        Notification::make()
                            ->success()
                            ->title("Выдано {$data['amount']} токенов")
                            ->send();
                    }),
                Action::make('grantItem')
                    ->label('Предмет')
                    ->schema([
                        Select::make('item_id')
                            ->label('Предмет')
                            ->options(fn (): array => Item::query()
                                ->active()
                                ->orderBy('name')
                                ->get(['id', 'name', 'price', 'rarity'])
                                ->mapWithKeys(fn (Item $item): array => [
                                    $item->id => $item->name.' / '.(Item::RARITIES[$item->rarity] ?? $item->rarity).' / '.$item->price.' ток.',
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Количество')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(1)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $item = Item::query()->findOrFail((int) $data['item_id']);
                        $quantity = (int) $data['quantity'];

                        app(ShopService::class)->grantItem($record, $item, $quantity);

                        Notification::make()
                            ->success()
                            ->title("Выдано предметов: {$quantity}")
                            ->send();
                    }),
            ]);
    }
}
