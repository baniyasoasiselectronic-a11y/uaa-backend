<?php

namespace App\Filament\Resources\EnquiryResource\Pages;

use App\Filament\Resources\EnquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEnquiry extends EditRecord
{
    protected static string $resource = EnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('whatsapp')->label('Reply on WhatsApp')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('success')
                ->visible(fn () => EnquiryResource::whatsappUrl($this->getRecord()) !== null)
                ->url(fn () => EnquiryResource::whatsappUrl($this->getRecord()), shouldOpenInNewTab: true),
            Actions\DeleteAction::make(),
        ];
    }
}
