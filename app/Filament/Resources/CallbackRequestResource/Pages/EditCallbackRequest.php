<?php

namespace App\Filament\Resources\CallbackRequestResource\Pages;

use App\Filament\Resources\CallbackRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCallbackRequest extends EditRecord
{
    protected static string $resource = CallbackRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
