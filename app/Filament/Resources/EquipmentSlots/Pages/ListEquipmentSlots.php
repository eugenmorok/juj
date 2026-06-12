<?php

namespace App\Filament\Resources\EquipmentSlots\Pages;

use App\Filament\Resources\EquipmentSlots\EquipmentSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentSlots extends ListRecords
{
    protected static string $resource = EquipmentSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
