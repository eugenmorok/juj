<?php

namespace App\Filament\Resources\BattleArenas;

use App\Filament\Resources\BattleArenas\Pages\CreateBattleArena;
use App\Filament\Resources\BattleArenas\Pages\EditBattleArena;
use App\Filament\Resources\BattleArenas\Pages\ListBattleArenas;
use App\Filament\Resources\BattleArenas\Schemas\BattleArenaForm;
use App\Filament\Resources\BattleArenas\Tables\BattleArenasTable;
use App\Models\BattleArena;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BattleArenaResource extends Resource
{
    protected static ?string $model = BattleArena::class;

    protected static ?string $slug = 'battle-arenas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Локации арен';

    protected static string|UnitEnum|null $navigationGroup = 'Баланс';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'локация арены';

    protected static ?string $pluralModelLabel = 'локации арен';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BattleArenaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BattleArenasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBattleArenas::route('/'),
            'create' => CreateBattleArena::route('/create'),
            'edit' => EditBattleArena::route('/{record}/edit'),
        ];
    }
}
