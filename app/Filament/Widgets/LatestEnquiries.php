<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EnquiryResource;
use App\Models\Enquiry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestEnquiries extends BaseWidget
{
    protected static ?string $heading = 'Latest enquiries';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Enquiry::query()->latest('id')->limit(6))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->since(),
                Tables\Columns\TextColumn::make('source')->label('From')->badge()
                    ->formatStateUsing(fn (?string $state) => EnquiryResource::SOURCES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) { 'website' => 'primary', 'ai_assistant' => 'info', 'whatsapp_click' => 'success', 'call_click' => 'warning', default => 'gray' }),
                Tables\Columns\TextColumn::make('name')->limit(22),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) { 'new' => 'danger', 'contacted' => 'warning', default => 'success' }),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')->iconButton()->icon('heroicon-o-chat-bubble-left-ellipsis')->color('success')->tooltip('Reply on WhatsApp')
                    ->visible(fn (Enquiry $record) => EnquiryResource::whatsappUrl($record) !== null)
                    ->url(fn (Enquiry $record) => EnquiryResource::whatsappUrl($record), shouldOpenInNewTab: true),
                Tables\Actions\Action::make('open')->iconButton()->icon('heroicon-o-arrow-top-right-on-square')->tooltip('Open')
                    ->url(fn (Enquiry $record) => EnquiryResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No enquiries yet')
            ->emptyStateDescription('New enquiries from the website will appear here.');
    }
}
