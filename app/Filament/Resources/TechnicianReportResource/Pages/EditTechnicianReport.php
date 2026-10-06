<?php

namespace App\Filament\Resources\TechnicianReportResource\Pages;

use App\Filament\Resources\TechnicianReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechnicianReport extends EditRecord
{
    protected static string $resource = TechnicianReportResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
