<?php

namespace App\Filament\Resources\BattleArenas\Pages;

use App\Filament\Resources\BattleArenas\BattleArenaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBattleArenas extends ListRecords
{
    protected static string $resource = BattleArenaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
