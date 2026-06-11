<?php

namespace App\Filament\Resources\CreatureSpecies\Pages;

use App\Filament\Resources\CreatureSpecies\CreatureSpeciesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCreatureSpecies extends ListRecords
{
    protected static string $resource = CreatureSpeciesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
