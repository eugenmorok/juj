<?php

namespace App\Filament\Resources\CreatureSpecies\Pages;

use App\Filament\Resources\CreatureSpecies\CreatureSpeciesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCreatureSpecies extends EditRecord
{
    protected static string $resource = CreatureSpeciesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
