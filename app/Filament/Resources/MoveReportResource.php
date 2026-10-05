<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MoveReportResource\Pages;
use App\Models\MoveReport;
use App\Support\MoveInspection;
use App\Support\UploadsToCloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class MoveReportResource extends Resource
{
    protected static ?string $model = MoveReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Customer Care';

    protected static ?string $navigationLabel = 'Move In / Move Out';

    protected static ?string $modelLabel = 'inspection';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $unitTypes = ['Apartment', 'Studio', 'Villa', 'Shop', 'Office', 'Other'];
        $beds = ['Studio', '1 Bedroom', '2 Bedroom', '3 Bedroom', '4 Bedroom', '5 Bedroom', 'N/A'];

        $roomSections = [];
        foreach (MoveInspection::rooms() as $roomId => $room) {
            $roomSections[] = Forms\Components\Section::make($room['name'])
                ->statePath("rooms.{$roomId}")
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->hiddenLabel()
                        ->default(MoveInspection::defaultItems($roomId))
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(12)
                        ->schema([
                            Forms\Components\TextInput::make('label')->hiddenLabel()->disabled()->dehydrated()->columnSpan(3),
                            Forms\Components\ToggleButtons::make('status')
                                ->hiddenLabel()
                                ->inline()
                                ->options(MoveInspection::STATUSES)
                                ->colors(['ok' => 'success', 'maintenance' => 'warning', 'damaged' => 'danger'])
                                ->default('ok')
                                ->live()
                                ->columnSpan(4),
                            Forms\Components\TextInput::make('notes')->hiddenLabel()->placeholder('Notes / description')->maxLength(255)->columnSpan(3),
                            Forms\Components\TextInput::make('price')
                                ->hiddenLabel()
                                ->placeholder('Charge')
                                ->prefix('AED')
                                ->numeric()
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->visible(fn (Get $get) => ($get('status') ?? 'ok') !== 'ok')
                                ->columnSpan(2),
                        ]),
                    UploadsToCloudinary::apply(
                        Forms\Components\FileUpload::make('photos')
                            ->label('Photos — '.$room['name'])
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->imagePreviewHeight('100'),
                        'move-reports',
                    ),
                ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Inspection')->columns(3)->schema([
                Forms\Components\ToggleButtons::make('type')
                    ->label('Inspection type')
                    ->inline()
                    ->options(MoveReport::TYPES)
                    ->colors(['move_in' => 'success', 'move_out' => 'warning'])
                    ->default('move_in')
                    ->required(),
                Forms\Components\DatePicker::make('report_date')->label('Inspection date')->default(now())->required(),
                Forms\Components\TextInput::make('inspector')
                    ->default(fn () => auth()->user()?->name)
                    ->datalist(['Jaseel'])
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference')
                    ->placeholder('Generated automatically')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['edit']),
            ]),
            Forms\Components\Section::make('Property & unit')->columns(3)->schema([
                Forms\Components\Select::make('property_id')
                    ->label('Property')
                    ->relationship('property', 'title')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('unit_label')->label('Unit number')->placeholder('e.g. 101')->required()->maxLength(100),
                Forms\Components\Select::make('unit_type')->options(array_combine($unitTypes, $unitTypes)),
                Forms\Components\Select::make('beds')->label('Bedrooms')->options(array_combine($beds, $beds)),
            ]),
            Forms\Components\Section::make('Tenant')->columns(3)->schema([
                Forms\Components\TextInput::make('tenant_name')->label('Tenant full name')->required()->columnSpan(2)->maxLength(255),
                Forms\Components\TextInput::make('tenant_phone')->label('Phone / mobile')->tel()->maxLength(50),
                Forms\Components\TextInput::make('tenant_email')->label('Email')->email()->maxLength(255),
                Forms\Components\TextInput::make('contract_no')->label('Contract no.')->placeholder('e.g. UAA-2024-1234')->maxLength(100),
                Forms\Components\TextInput::make('keys_count')->label('Keys returned / handed')->numeric()->minValue(0)->default(0),
                Forms\Components\TextInput::make('parking_cards')->label('Parking cards')->numeric()->minValue(0)->default(0),
            ]),
            Forms\Components\Section::make('Room inspection')
                ->description('Mark each item Good, Maintenance or Damaged. A charge can be added to anything that is not Good.')
                ->schema([
                    Forms\Components\Placeholder::make('live_totals')
                        ->hiddenLabel()
                        ->content(function (Get $get) {
                            $t = MoveInspection::totals($get('rooms'));

                            return new HtmlString(sprintf(
                                '<div style="font-weight:600">Subtotal AED %s &nbsp;·&nbsp; VAT 5%% AED %s &nbsp;·&nbsp; <span style="color:#b00020">Total due AED %s</span></div>',
                                number_format($t['subtotal'], 2),
                                number_format($t['vat_amount'], 2),
                                number_format($t['total_amount'], 2),
                            ));
                        }),
                    ...$roomSections,
                ]),
            Forms\Components\Section::make('Comments & signatures')->columns(2)->schema([
                Forms\Components\Textarea::make('notes')->label('Comments')->rows(3)->columnSpanFull(),
                Forms\Components\ViewField::make('tenant_signature')->label('Tenant signature')->view('filament.forms.signature-pad'),
                Forms\Components\ViewField::make('inspector_signature')->label('Inspector signature')->view('filament.forms.signature-pad'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('report_date', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('property'))
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label('Ref #')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => MoveReport::TYPES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'move_in' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('report_date')->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('tenant_name')->label('Tenant')->searchable()->description(fn ($record) => $record->tenant_phone),
                Tables\Columns\TextColumn::make('unit_label')
                    ->label('Property / Unit')
                    ->formatStateUsing(fn ($state, $record) => trim(($record->property?->title ?? '—').($state ? ' — '.$state : '')))
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit_type')->label('Unit type'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'AED '.number_format((float) $state, 2))
                    ->color('danger')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('inspector'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(MoveReport::TYPES),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (MoveReport $record) => route('move-reports.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->url(fn (MoveReport $record) => route('move-reports.print', [$record, 'print' => 1]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMoveReports::route('/'),
            'create' => Pages\CreateMoveReport::route('/create'),
            'edit' => Pages\EditMoveReport::route('/{record}/edit'),
        ];
    }
}
