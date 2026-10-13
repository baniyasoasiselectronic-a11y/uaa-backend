<?php

namespace App\Filament\Resources\MoveReportResource\Pages;

use App\Filament\Resources\EnquiryResource;
use App\Filament\Resources\MoveReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMoveReport extends ViewRecord
{
    protected static string $resource = MoveReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')->label('PDF / Print')->icon('heroicon-o-printer')
                ->url(fn () => MoveReportResource::printUrl($this->getRecord(), true), shouldOpenInNewTab: true),
            Actions\Action::make('whatsapp')->label('WhatsApp the tenant')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('success')
                ->visible(fn () => EnquiryResource::whatsappNumber($this->getRecord()->tenant_phone) !== null)
                ->url(fn () => 'https://wa.me/'.EnquiryResource::whatsappNumber($this->getRecord()->tenant_phone), shouldOpenInNewTab: true),
        ];
    }
}
