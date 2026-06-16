<?php

namespace App\Filament\Resources\Battles;

use App\Filament\Resources\Battles\Pages\ListBattles;
use App\Filament\Resources\Battles\Tables\BattlesTable;
use App\Models\Battle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BattleResource extends Resource
{
    protected static ?string $model = Battle::class;

    protected static ?string $slug = 'battles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Бои';

    protected static string|UnitEnum|null $navigationGroup = 'Арена';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'бой';

    protected static ?string $pluralModelLabel = 'бои';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return BattlesTable::configure($table);
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
            'index' => ListBattles::route('/'),
        ];
    }
}
