<?php

namespace App\Filament\Resources\MoveReportResource\Pages;

use App\Filament\Resources\MoveReportResource;
use App\Support\MoveInspection;
use Filament\Resources\Pages\CreateRecord;

class CreateMoveReport extends CreateRecord
{
    protected static string $resource = MoveReportResource::class;

    protected static ?string $title = 'New inspection';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return array_merge($data, MoveInspection::totals($data['rooms'] ?? null));
    }
}
