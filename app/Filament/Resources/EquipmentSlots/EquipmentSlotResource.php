<?php

namespace App\Filament\Resources\EquipmentSlots;

use App\Filament\Resources\EquipmentSlots\Pages\CreateEquipmentSlot;
use App\Filament\Resources\EquipmentSlots\Pages\EditEquipmentSlot;
use App\Filament\Resources\EquipmentSlots\Pages\ListEquipmentSlots;
use App\Filament\Resources\EquipmentSlots\Schemas\EquipmentSlotForm;
use App\Filament\Resources\EquipmentSlots\Tables\EquipmentSlotsTable;
use App\Models\EquipmentSlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EquipmentSlotResource extends Resource
{
    protected static ?string $model = EquipmentSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Слоты экипировки';

    protected static string|UnitEnum|null $navigationGroup = 'Справочники';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'слот экипировки';

    protected static ?string $pluralModelLabel = 'слоты экипировки';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EquipmentSlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentSlotsTable::configure($table);
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
            'index' => ListEquipmentSlots::route('/'),
            'create' => CreateEquipmentSlot::route('/create'),
            'edit' => EditEquipmentSlot::route('/{record}/edit'),
        ];
    }
}
