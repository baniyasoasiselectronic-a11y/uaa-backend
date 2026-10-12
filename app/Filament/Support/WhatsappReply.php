<?php

namespace App\Filament\Support;

use App\Filament\Resources\EnquiryResource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

/**
 * "Reply on WhatsApp" and "Call" buttons for admin lists of people who left a phone number
 * (callback requests, contact requests). WhatsApp opens in a new tab with a ready-to-send message.
 */
class WhatsappReply
{
    public static function url(Model $record, string $intro): ?string
    {
        $n = EnquiryResource::whatsappNumber($record->phone ?? null);
        if (! $n) {
            return null;
        }
        $first = trim(explode(' ', (string) ($record->name ?? ''))[0]);

        return 'https://wa.me/'.$n.'?text='.rawurlencode('Hello '.($first ?: 'there').', this is United Arab Agencies. '.$intro);
    }

    /** @return array<int, Tables\Actions\Action> */
    public static function tableActions(\Closure $intro): array
    {
        return [
            Tables\Actions\Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('success')
                ->visible(fn ($record) => self::url($record, '') !== null)
                ->url(fn ($record) => self::url($record, $intro($record)), shouldOpenInNewTab: true),
            Tables\Actions\Action::make('call')->label('Call')->icon('heroicon-o-phone')->color('gray')
                ->visible(fn ($record) => filled($record->phone))
                ->url(fn ($record) => 'tel:'.preg_replace('/[^\d+]/', '', (string) $record->phone)),
        ];
    }

    public static function phoneColumn(\Closure $intro): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('phone')->searchable()->placeholder('—')
            ->icon(fn ($record) => self::url($record, '') ? 'heroicon-o-chat-bubble-left-ellipsis' : null)->iconColor('success')
            ->url(fn ($record) => self::url($record, $intro($record)), shouldOpenInNewTab: true);
    }
}
