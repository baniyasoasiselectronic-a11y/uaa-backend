<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ComplaintResource;
use App\Models\Complaint;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestComplaints extends BaseWidget
{
    use \App\Filament\Concerns\HiddenFromEnquiriesOnly;

    protected static ?string $heading = 'Latest complaints';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Complaint::query()->latest('id')->limit(6))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('No.')
                    ->state(fn (Complaint $r) => $r->complaint_number ?: $r->ticket_number)->weight('semibold'),
                Tables\Columns\TextColumn::make('name')->label('Tenant')->limit(18),
                Tables\Columns\TextColumn::make('unit')->label('Unit')
                    ->state(fn (Complaint $r) => trim(($r->property_name ? $r->property_name.' · ' : '').($r->unit_label ?: ''), ' ·'))->limit(24)->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) { 'new' => 'danger', 'assigned', 'in_progress' => 'warning', default => 'success' }),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->iconButton()->icon('heroicon-o-arrow-top-right-on-square')->tooltip('Open')
                    ->url(fn (Complaint $record) => ComplaintResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No complaints yet');
    }
}
