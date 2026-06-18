<?php

namespace App\Filament\Resources\BattleArenas\Pages;

use App\Filament\Resources\BattleArenas\BattleArenaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBattleArena extends EditRecord
{
    protected static string $resource = BattleArenaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
