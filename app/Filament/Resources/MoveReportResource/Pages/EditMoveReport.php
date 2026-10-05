<?php

namespace App\Filament\Resources\MoveReportResource\Pages;

use App\Filament\Resources\MoveReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMoveReport extends EditRecord
{
    protected static string $resource = MoveReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('Print / PDF')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('move-reports.print', $this->record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
