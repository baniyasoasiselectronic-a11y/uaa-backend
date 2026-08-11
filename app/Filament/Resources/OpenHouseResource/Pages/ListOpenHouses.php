<?php

namespace App\Filament\Resources\OpenHouseResource\Pages;

use App\Filament\Resources\OpenHouseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOpenHouses extends ListRecords
{
    protected static string $resource = OpenHouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
