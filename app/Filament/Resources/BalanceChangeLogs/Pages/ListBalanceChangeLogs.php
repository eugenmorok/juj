<?php

namespace App\Filament\Resources\BalanceChangeLogs\Pages;

use App\Filament\Resources\BalanceChangeLogs\BalanceChangeLogResource;
use Filament\Resources\Pages\ListRecords;

class ListBalanceChangeLogs extends ListRecords
{
    protected static string $resource = BalanceChangeLogResource::class;
}
