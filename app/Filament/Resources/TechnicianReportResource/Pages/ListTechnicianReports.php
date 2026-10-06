<?php

namespace App\Filament\Resources\TechnicianReportResource\Pages;

use App\Filament\Resources\TechnicianReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechnicianReports extends ListRecords
{
    protected static string $resource = TechnicianReportResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
