<?php

namespace App\Filament\Resources\CreatureSpecies;

use App\Filament\Resources\CreatureSpecies\Pages\CreateCreatureSpecies;
use App\Filament\Resources\CreatureSpecies\Pages\EditCreatureSpecies;
use App\Filament\Resources\CreatureSpecies\Pages\ListCreatureSpecies;
use App\Filament\Resources\CreatureSpecies\Schemas\CreatureSpeciesForm;
use App\Filament\Resources\CreatureSpecies\Tables\CreatureSpeciesTable;
use App\Models\CreatureSpecies;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CreatureSpeciesResource extends Resource
{
    protected static ?string $model = CreatureSpecies::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Виды сущностей';

    protected static string|UnitEnum|null $navigationGroup = 'Справочники';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'вид сущности';

    protected static ?string $pluralModelLabel = 'виды сущностей';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CreatureSpeciesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreatureSpeciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreatureSpecies::route('/'),
            'create' => CreateCreatureSpecies::route('/create'),
            'edit' => EditCreatureSpecies::route('/{record}/edit'),
        ];
    }
}
