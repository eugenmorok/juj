<?php

namespace App\Filament\Resources\CreatureTypes;

use App\Filament\Resources\CreatureTypes\Pages\CreateCreatureType;
use App\Filament\Resources\CreatureTypes\Pages\EditCreatureType;
use App\Filament\Resources\CreatureTypes\Pages\ListCreatureTypes;
use App\Filament\Resources\CreatureTypes\Schemas\CreatureTypeForm;
use App\Filament\Resources\CreatureTypes\Tables\CreatureTypesTable;
use App\Models\CreatureType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CreatureTypeResource extends Resource
{
    protected static ?string $model = CreatureType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Типы сущностей';

    protected static string|UnitEnum|null $navigationGroup = 'Справочники';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'тип сущности';

    protected static ?string $pluralModelLabel = 'типы сущностей';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CreatureTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreatureTypesTable::configure($table);
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
            'index' => ListCreatureTypes::route('/'),
            'create' => CreateCreatureType::route('/create'),
            'edit' => EditCreatureType::route('/{record}/edit'),
        ];
    }
}
