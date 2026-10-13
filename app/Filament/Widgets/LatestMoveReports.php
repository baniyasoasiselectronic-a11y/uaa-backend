<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MoveReportResource;
use App\Models\MoveReport;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestMoveReports extends BaseWidget
{
    protected static ?string $heading = 'Latest move in / out reports';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(MoveReport::query()->latest('id')->limit(6))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Submitted')->since(),
                Tables\Columns\TextColumn::make('reference')->weight('semibold'),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (?string $state) => MoveReport::TYPES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) { 'move_in' => 'success', 'move_out' => 'warning', 'renewal' => 'info', default => 'gray' }),
                Tables\Columns\TextColumn::make('building')->label('Building')
                    ->state(fn (MoveReport $r) => $r->property_name ?: $r->property?->title)->placeholder('—'),
                Tables\Columns\TextColumn::make('unit_label')->label('Unit')->placeholder('—'),
                Tables\Columns\TextColumn::make('tenant_name')->label('Tenant'),
                Tables\Columns\TextColumn::make('inspector')->placeholder('—'),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('AED')->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->label('Open')->icon('heroicon-o-eye')
                    ->url(fn (MoveReport $record) => MoveReportResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No inspections yet')
            ->emptyStateDescription('Reports submitted on the website inspection page appear here.');
    }
}
