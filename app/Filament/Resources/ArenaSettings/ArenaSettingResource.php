<?php

namespace App\Filament\Resources\ArenaSettings;

use App\Filament\Resources\ArenaSettings\Pages\CreateArenaSetting;
use App\Filament\Resources\ArenaSettings\Pages\EditArenaSetting;
use App\Filament\Resources\ArenaSettings\Pages\ListArenaSettings;
use App\Filament\Resources\ArenaSettings\Schemas\ArenaSettingForm;
use App\Filament\Resources\ArenaSettings\Tables\ArenaSettingsTable;
use App\Models\ArenaSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ArenaSettingResource extends Resource
{
    protected static ?string $model = ArenaSetting::class;

    protected static ?string $slug = 'arena-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Настройки арены';

    protected static string|UnitEnum|null $navigationGroup = 'Баланс';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'настройка арены';

    protected static ?string $pluralModelLabel = 'настройки арены';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ArenaSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArenaSettingsTable::configure($table);
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
            'index' => ListArenaSettings::route('/'),
            'create' => CreateArenaSetting::route('/create'),
            'edit' => EditArenaSetting::route('/{record}/edit'),
        ];
    }
}
