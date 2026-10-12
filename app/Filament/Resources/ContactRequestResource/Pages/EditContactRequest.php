<?php

namespace App\Filament\Resources\ContactRequestResource\Pages;

use App\Filament\Resources\ContactRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactRequest extends EditRecord
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('whatsapp')->label('Reply on WhatsApp')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('success')
                ->visible(fn () => \App\Filament\Support\WhatsappReply::url($this->getRecord(), '') !== null)
                ->url(fn () => \App\Filament\Support\WhatsappReply::url($this->getRecord(), (fn ($r) => 'Thank you for contacting us'.($r->subject ? ' about "'.$r->subject.'"' : '').'. How can we help you?')($this->getRecord())), shouldOpenInNewTab: true),
            Actions\DeleteAction::make(),
        ];
    }
}
