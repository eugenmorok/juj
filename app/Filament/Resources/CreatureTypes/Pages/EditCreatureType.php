<?php

namespace App\Filament\Resources\CreatureTypes\Pages;

use App\Filament\Resources\CreatureTypes\CreatureTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCreatureType extends EditRecord
{
    protected static string $resource = CreatureTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
