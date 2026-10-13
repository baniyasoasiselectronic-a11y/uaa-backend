<?php

namespace App\Filament\Resources\MoveReportResource\Pages;

use App\Filament\Resources\MoveReportResource;
use Filament\Resources\Pages\ListRecords;

class ListMoveReports extends ListRecords
{
    protected static string $resource = MoveReportResource::class;

    protected ?string $subheading = "Every Move In / Move Out inspection submitted on the website, plus the old reports. The newest is at the top; the number on the menu is today's reports.";
}
