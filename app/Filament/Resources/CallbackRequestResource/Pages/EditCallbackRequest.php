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
            Actions\Action::make('whatsapp')->label('Reply on WhatsApp')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('success')
                ->visible(fn () => \App\Filament\Support\WhatsappReply::url($this->getRecord(), '') !== null)
                ->url(fn () => \App\Filament\Support\WhatsappReply::url($this->getRecord(), (fn ($r) => 'Thank you for asking us to call you back. Is now a good time to talk?')($this->getRecord())), shouldOpenInNewTab: true),
            Actions\DeleteAction::make(),
        ];
    }
}
