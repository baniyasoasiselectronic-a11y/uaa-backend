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

    /** An enquiries-only account (the sales team) gets the big version: full width, more rows, phone + message. */
    private function big(): bool
    {
        return auth()->user()?->isEnquiriesOnly() ?? false;
    }

    public function getColumnSpan(): int|string|array
    {
        return $this->big() ? 'full' : $this->columnSpan;
    }

    /** For the sales team the table is the first thing under the greeting (everyone else keeps the normal order). */
    public static function getSort(): int
    {
        return (auth()->user()?->isEnquiriesOnly() ?? false) ? -1 : (static::$sort ?? 5);
    }

    public function table(Table $table): Table
    {
        $big = $this->big();

        return $table
            ->query(Enquiry::query()->latest('id')->limit($big ? 20 : 6))
            ->paginated(false)
            ->headerActions($big ? [
                Tables\Actions\Action::make('all')->label('View all enquiries')->icon('heroicon-o-inbox-arrow-down')
                    ->url(EnquiryResource::getUrl('index')),
            ] : [])
            ->columns(array_values(array_filter([
                Tables\Columns\TextColumn::make('created_at')->label('When')->since()
                    ->tooltip(fn (Enquiry $record) => $record->created_at?->format('d M Y, H:i')),
                Tables\Columns\TextColumn::make('source')->label('From')->badge()
                    ->formatStateUsing(fn (?string $state) => EnquiryResource::SOURCES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) { 'website' => 'primary', 'ai_assistant' => 'info', 'whatsapp_click' => 'success', 'call_click' => 'warning', default => 'gray' }),
                Tables\Columns\TextColumn::make('name')->limit($big ? 40 : 22),
                $big ? Tables\Columns\TextColumn::make('phone')->placeholder('—')
                    ->icon(fn (Enquiry $record) => EnquiryResource::whatsappUrl($record) ? 'heroicon-o-chat-bubble-left-ellipsis' : null)->iconColor('success')
                    ->url(fn (Enquiry $record) => EnquiryResource::whatsappUrl($record), shouldOpenInNewTab: true) : null,
                $big ? Tables\Columns\TextColumn::make('message')->limit(90)->wrap()->tooltip(fn (Enquiry $record) => $record->message) : null,
                $big ? Tables\Columns\TextColumn::make('page')->label('Page / building')->limit(30)->placeholder('—') : null,
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) { 'new' => 'danger', 'contacted' => 'warning', default => 'success' }),
            ])))
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
