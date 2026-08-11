<?php

namespace App\Filament\Resources\ListWithUsRequestResource\Pages;

use App\Filament\Resources\ListWithUsRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListListWithUsRequests extends ListRecords
{
    protected static string $resource = ListWithUsRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
