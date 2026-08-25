<?php

namespace App\Filament\Resources\UpcomingProjectResource\Pages;

use App\Filament\Resources\UpcomingProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUpcomingProject extends EditRecord
{
    protected static string $resource = UpcomingProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
