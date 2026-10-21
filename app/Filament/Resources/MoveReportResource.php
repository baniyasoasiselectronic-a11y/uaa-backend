<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MoveReportResource\Pages;
use App\Models\MoveReport;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

/**
 * Every Move In / Move Out (and Renewal / Legal) inspection report: the ones inspectors submit on the
 * website's inspection page, plus the old reports carried over from the WordPress site.
 */
class MoveReportResource extends Resource
{
    use \App\Filament\Concerns\RestrictedByRole;

    protected static ?string $model = MoveReport::class;

    protected static ?string $slug = 'move-reports';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Customer Care';

    protected static ?string $navigationLabel = 'Move In / Out Reports';

    protected static ?string $modelLabel = 'move in/out report';

    protected static ?string $pluralModelLabel = 'move in/out reports';

    protected static ?int $navigationSort = 3;

    /** Badge = reports submitted today, so a new one is noticed straight away. */
    public static function getNavigationBadge(): ?string
    {
        $n = MoveReport::whereDate('created_at', today())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Submitted today';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function printUrl(MoveReport $r, bool $print = false): string
    {
        return URL::temporarySignedRoute('move-reports.public', now()->addDays(2), ['moveReport' => $r->id] + ($print ? ['print' => 1] : []));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Submitted')->since()->sortable()
                    ->tooltip(fn (MoveReport $r) => $r->created_at?->format('d M Y, H:i')),
                Tables\Columns\TextColumn::make('reference')->label('Reference')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge()
                    ->formatStateUsing(fn (?string $state) => MoveReport::TYPES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) { 'move_in' => 'success', 'move_out' => 'warning', 'renewal' => 'info', default => 'gray' }),
                Tables\Columns\TextColumn::make('report_date')->label('Inspection date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('building')->label('Building')
                    ->state(fn (MoveReport $r) => $r->property_name ?: $r->property?->title)->searchable(['property_name'])->placeholder('—'),
                Tables\Columns\TextColumn::make('unit_label')->label('Unit')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('tenant_name')->label('Tenant')->searchable(),
                Tables\Columns\TextColumn::make('tenant_phone')->label('Phone')->searchable()->placeholder('—')
                    ->icon(fn (MoveReport $r) => EnquiryResource::whatsappNumber($r->tenant_phone) ? 'heroicon-o-chat-bubble-left-ellipsis' : null)->iconColor('success')
                    ->url(fn (MoveReport $r) => ($n = EnquiryResource::whatsappNumber($r->tenant_phone)) ? 'https://wa.me/'.$n : null, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('inspector')->label('Inspector')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('total_amount')->label('Total (incl. VAT)')->money('AED')->sortable()->placeholder('—'),
                Tables\Columns\IconColumn::make('emailed')->label('E-mailed')->boolean()
                    ->state(fn (MoveReport $r) => $r->emailed_at !== null)->toggleable(),
                Tables\Columns\TextColumn::make('source')->label('Source')->badge()
                    ->state(fn (MoveReport $r) => $r->legacy_entry_id ? 'Old site' : 'New site')
                    ->color(fn (string $state) => $state === 'Old site' ? 'gray' : 'primary')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Type')->options(MoveReport::TYPES),
                Tables\Filters\SelectFilter::make('inspector')->label('Inspector')
                    ->options(fn () => MoveReport::query()->whereNotNull('inspector')->distinct()->orderBy('inspector')->pluck('inspector', 'inspector')->all()),
                Tables\Filters\Filter::make('report_date')->label('Inspection date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('report_date', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('report_date', '<=', $v))),
                Tables\Filters\TernaryFilter::make('legacy_entry_id')->label('Source')
                    ->placeholder('All')->trueLabel('Old site')->falseLabel('New site')
                    ->queries(true: fn (Builder $q) => $q->whereNotNull('legacy_entry_id'), false: fn (Builder $q) => $q->whereNull('legacy_entry_id'), blank: fn (Builder $q) => $q),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Open'),
                Tables\Actions\Action::make('pdf')->label('PDF / Print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (MoveReport $r) => self::printUrl($r, true), shouldOpenInNewTab: true),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Report')->columns(4)->schema([
                Infolists\Components\TextEntry::make('reference')->copyable(),
                Infolists\Components\TextEntry::make('type')->badge()->formatStateUsing(fn (?string $state) => MoveReport::TYPES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) { 'move_in' => 'success', 'move_out' => 'warning', 'renewal' => 'info', default => 'gray' }),
                Infolists\Components\TextEntry::make('report_date')->label('Inspection date')->date('d M Y'),
                Infolists\Components\TextEntry::make('inspector')->placeholder('—'),
                Infolists\Components\TextEntry::make('created_at')->label('Submitted')->dateTime('d M Y, H:i'),
                Infolists\Components\TextEntry::make('emailed_at')->label('E-mailed')->dateTime('d M Y, H:i')->placeholder('Not e-mailed'),
                Infolists\Components\TextEntry::make('contract_no')->label('Contract no.')->placeholder('—'),
                Infolists\Components\TextEntry::make('source')->label('Source')->state(fn (MoveReport $r) => $r->legacy_entry_id ? 'Old site' : 'New site'),
            ]),
            Infolists\Components\Section::make('Unit & tenant')->columns(4)->schema([
                Infolists\Components\TextEntry::make('building')->label('Building')->state(fn (MoveReport $r) => $r->property_name ?: $r->property?->title)->placeholder('—'),
                Infolists\Components\TextEntry::make('unit_label')->label('Unit')->placeholder('—'),
                Infolists\Components\TextEntry::make('unit_type')->label('Unit type')->placeholder('—'),
                Infolists\Components\TextEntry::make('beds')->label('Beds')->placeholder('—'),
                Infolists\Components\TextEntry::make('tenant_name')->label('Tenant'),
                Infolists\Components\TextEntry::make('tenant_phone')->label('Phone')->placeholder('—')
                    ->url(fn (MoveReport $r) => ($n = EnquiryResource::whatsappNumber($r->tenant_phone)) ? 'https://wa.me/'.$n : null, shouldOpenInNewTab: true),
                Infolists\Components\TextEntry::make('tenant_email')->label('Email')->placeholder('—'),
                Infolists\Components\TextEntry::make('keys')->label('Keys / parking cards')
                    ->state(fn (MoveReport $r) => ($r->keys_count ?? '—').' / '.($r->parking_cards ?? '—')),
            ]),
            Infolists\Components\Section::make('Charges')->columns(3)->schema([
                Infolists\Components\TextEntry::make('subtotal')->money('AED'),
                Infolists\Components\TextEntry::make('vat_amount')->label('VAT 5%')->money('AED'),
                Infolists\Components\TextEntry::make('total_amount')->label('Total')->money('AED')->weight('bold'),
                Infolists\Components\TextEntry::make('notes')->label('Comments')->placeholder('—')->columnSpanFull(),
            ]),
            Infolists\Components\Section::make('Full report (room by room, photos and signatures)')->schema([
                Infolists\Components\ViewEntry::make('full')->hiddenLabel()->view('filament.move-report-frame')->columnSpanFull(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMoveReports::route('/'),
            'view' => Pages\ViewMoveReport::route('/{record}'),
        ];
    }
}
