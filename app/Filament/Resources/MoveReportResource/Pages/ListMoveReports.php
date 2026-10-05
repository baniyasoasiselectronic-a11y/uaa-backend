<?php

namespace App\Filament\Resources\MoveReportResource\Pages;

use App\Filament\Resources\MoveReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMoveReports extends ListRecords
{
    protected static string $resource = MoveReportResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New report')];
    }
}
