<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MoveReportResource\Pages;
use App\Models\MoveReport;
use App\Support\UploadsToCloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MoveReportResource extends Resource
{
    protected static ?string $model = MoveReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Customer Care';

    protected static ?string $navigationLabel = 'Move In / Move Out';

    protected static ?string $modelLabel = 'move report';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $unitTypes = ['Apartment', 'Studio', 'Villa', 'Shop', 'Office', 'Other'];

        return $form->schema([
            Forms\Components\Section::make('Report')->columns(3)->schema([
                Forms\Components\Select::make('type')
                    ->options(MoveReport::TYPES)
                    ->default('move_in')
                    ->required(),
                Forms\Components\DatePicker::make('report_date')
                    ->default(now())
                    ->required(),
                Forms\Components\TextInput::make('inspector')
                    ->default(fn () => auth()->user()?->name)
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference')
                    ->placeholder('Generated automatically')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['edit', 'view']),
            ]),
            Forms\Components\Section::make('Tenant')->columns(3)->schema([
                Forms\Components\TextInput::make('tenant_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('tenant_phone')->tel()->maxLength(50),
                Forms\Components\TextInput::make('tenant_email')->email()->maxLength(255),
            ]),
            Forms\Components\Section::make('Property & unit')->columns(3)->schema([
                Forms\Components\Select::make('property_id')
                    ->label('Building / project')
                    ->relationship('property', 'title')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('unit_label')
                    ->label('Unit no.')
                    ->placeholder('e.g. 101')
                    ->maxLength(100),
                Forms\Components\Select::make('unit_type')
                    ->options(array_combine($unitTypes, $unitTypes)),
            ]),
            Forms\Components\Section::make('Readings & keys')->columns(3)->schema([
                Forms\Components\TextInput::make('electricity_reading')->label('Electricity meter')->maxLength(100),
                Forms\Components\TextInput::make('water_reading')->label('Water meter')->maxLength(100),
                Forms\Components\TextInput::make('keys_count')->label('Keys handed')->numeric()->minValue(0),
            ]),
            Forms\Components\Section::make('Inspection items & charges')
                ->description('One row per room / item checked. Anything with a charge is added to the report total.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->orderColumn('sort_order')
                        ->hiddenLabel()
                        ->addActionLabel('Add item')
                        ->columns(12)
                        ->defaultItems(0)
                        ->collapsible()
                        ->itemLabel(fn (array $state) => trim(($state['area'] ?? '').' — '.($state['description'] ?? ''), ' —') ?: null)
                        ->schema([
                            Forms\Components\TextInput::make('area')->placeholder('e.g. Living room')->columnSpan(3)->maxLength(255),
                            Forms\Components\TextInput::make('description')->label('Item / description')->required()->columnSpan(4)->maxLength(255),
                            Forms\Components\Select::make('condition')->options(MoveReport::CONDITIONS)->columnSpan(2),
                            Forms\Components\TextInput::make('charge')->label('Charge (AED)')->numeric()->default(0)->minValue(0)->columnSpan(3),
                            Forms\Components\TextInput::make('remarks')->columnSpan(12)->maxLength(255),
                        ]),
                ]),
            Forms\Components\Section::make('Photos & notes')->schema([
                UploadsToCloudinary::apply(
                    Forms\Components\FileUpload::make('photos')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->imagePreviewHeight('120')
                        ->helperText('Photos of the unit condition (damage, meters, etc.).'),
                    'move-reports',
                ),
                Forms\Components\Textarea::make('notes')->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('report_date', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['items', 'property']))
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
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->state(fn ($record) => 'AED '.number_format($record->total, 2))
                    ->color('danger')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('inspector'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(MoveReport::TYPES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->url(fn (MoveReport $record) => route('move-reports.print', $record))
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
