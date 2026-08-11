<?php

namespace App\Filament\Resources\ListWithUsRequestResource\Pages;

use App\Filament\Resources\ListWithUsRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditListWithUsRequest extends EditRecord
{
    protected static string $resource = ListWithUsRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
