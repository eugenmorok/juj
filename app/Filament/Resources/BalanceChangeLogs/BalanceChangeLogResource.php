<?php

namespace App\Filament\Resources\BalanceChangeLogs;

use App\Filament\Resources\BalanceChangeLogs\Pages\ListBalanceChangeLogs;
use App\Filament\Resources\BalanceChangeLogs\Tables\BalanceChangeLogsTable;
use App\Models\BalanceChangeLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BalanceChangeLogResource extends Resource
{
    protected static ?string $model = BalanceChangeLog::class;

    protected static ?string $slug = 'balance-change-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Журнал баланса';

    protected static string|UnitEnum|null $navigationGroup = 'Баланс';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'запись журнала баланса';

    protected static ?string $pluralModelLabel = 'журнал баланса';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return BalanceChangeLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBalanceChangeLogs::route('/'),
        ];
    }
}
