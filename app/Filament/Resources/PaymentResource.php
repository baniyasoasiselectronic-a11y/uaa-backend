<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Complaint;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Payments for maintenance complaints (the old site charged a small fee per complaint through Stripe).
 * This is the record keeper only: nothing here connects to a payment provider yet — the gateway is
 * switched off, so new complaints are saved as "Not required". Old paid complaints are listed as they
 * were imported.
 */
class PaymentResource extends Resource
{
    use \App\Filament\Concerns\RestrictedByRole;

    protected static ?string $model = Complaint::class;

    protected static ?string $slug = 'payments';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Customer Care';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $modelLabel = 'payment';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('payment_status', ['paid', 'unpaid', 'free']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('complaint_number')->label('Complaint no.')
                    ->state(fn (Complaint $r) => $r->complaint_number ?: $r->ticket_number)->searchable(['complaint_number', 'ticket_number']),
                Tables\Columns\TextColumn::make('name')->label('Tenant')->searchable(),
                Tables\Columns\TextColumn::make('property_name')->label('Building')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('unit_label')->label('Unit')->placeholder('—'),
                Tables\Columns\TextColumn::make('payment_status')->label('Payment')->badge()
                    ->formatStateUsing(fn (string $state) => ['paid' => 'Paid', 'unpaid' => 'Unpaid', 'free' => 'Free (no-fee tenant)'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'paid' => 'success', 'unpaid' => 'danger', 'free' => 'info', default => 'gray' }),
                Tables\Columns\TextColumn::make('payment_amount')->label('Amount')->money('AED')->placeholder('—'),
                Tables\Columns\TextColumn::make('payment_ref')->label('Reference')->placeholder('—')->limit(24)->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')->label('Payment')
                    ->options(['paid' => 'Paid', 'unpaid' => 'Unpaid', 'free' => 'Free (no-fee tenant)']),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->label('Open complaint')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Complaint $r) => ComplaintResource::getUrl('edit', ['record' => $r])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
