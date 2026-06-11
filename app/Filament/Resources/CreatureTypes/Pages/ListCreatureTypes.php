<?php

namespace App\Filament\Resources\CreatureTypes\Pages;

use App\Filament\Resources\CreatureTypes\CreatureTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCreatureTypes extends ListRecords
{
    protected static string $resource = CreatureTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
