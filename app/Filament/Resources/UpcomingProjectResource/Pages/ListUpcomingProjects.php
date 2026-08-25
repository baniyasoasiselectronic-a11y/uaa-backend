<?php

namespace App\Filament\Resources\UpcomingProjectResource\Pages;

use App\Filament\Resources\UpcomingProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUpcomingProjects extends ListRecords
{
    protected static string $resource = UpcomingProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
