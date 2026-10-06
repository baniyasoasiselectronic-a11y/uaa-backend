<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractRenewalResource\Pages;
use App\Filament\Support\UnitPicker;
use App\Models\ContractRenewal;
use App\Support\UaaOracle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContractRenewalResource extends Resource
{
    protected static ?string $model = ContractRenewal::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Customer Care';

    protected static ?string $navigationLabel = 'Contract Renewals';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        $beds = ['Studio', '1 Bedroom', '2 Bedroom', '3 Bedroom', '4 Bedroom'];
        $staff = UaaOracle::lines('renewal_staff');

        return $form->schema([
            Forms\Components\Section::make('Tenancy')->columns(3)->schema([
                Forms\Components\DatePicker::make('report_date')->label('Date')->default(now())->required(),
                ...UnitPicker::components(),
                Forms\Components\Select::make('beds')->label('Bedrooms')->options(array_combine($beds, $beds)),
            ]),
            Forms\Components\Section::make('Tenant')->columns(3)->schema([
                Forms\Components\TextInput::make('tenant_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('tenant_phone')->label('Phone')->tel()->required()->maxLength(50),
                Forms\Components\TextInput::make('tenant_email')->label('Email')->email()->required()->maxLength(255),
            ]),
            Forms\Components\Section::make('Renewed contract')->columns(2)->schema([
                Forms\Components\FileUpload::make('contract_file')
                    ->label('Renewed contract (PDF or photo)')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->directory('renewal-contracts')
                    ->maxSize(15360)
                    ->openable()
                    ->downloadable()
                    ->required()
                    ->columnSpanFull(),
                $staff
                    ? Forms\Components\Select::make('renewed_by')->options(array_combine($staff, $staff))->required()
                    : Forms\Components\TextInput::make('renewed_by')->required()->maxLength(255),
                Forms\Components\Placeholder::make('spacer')->hiddenLabel()->content(''),
                Forms\Components\ViewField::make('tenant_signature')->label('Tenant signature')->view('filament.forms.signature-pad'),
                Forms\Components\ViewField::make('renewed_by_signature')->label('Signature (renewed by)')->view('filament.forms.signature-pad'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('report_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('report_date')->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('tenant_name')->label('Tenant')->searchable()->description(fn ($r) => $r->tenant_phone),
                Tables\Columns\TextColumn::make('property_name')
                    ->label('Property / Unit')
                    ->formatStateUsing(fn ($state, $r) => trim(($state ?: '—').($r->unit_label ? ' — '.$r->unit_label : '')))
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit_type')->label('Unit type'),
                Tables\Columns\TextColumn::make('beds')->label('Beds'),
                Tables\Columns\TextColumn::make('renewed_by')->label('Renewed by'),
                Tables\Columns\IconColumn::make('contract_file')
                    ->label('Contract')
                    ->icon(fn ($state) => $state ? 'heroicon-o-document-check' : 'heroicon-o-minus')
                    ->url(fn (\App\Models\ContractRenewal $r) => $r->contractUrl(), true),
                Tables\Columns\IconColumn::make('imported')->boolean()->label('Old record')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractRenewals::route('/'),
            'create' => Pages\CreateContractRenewal::route('/create'),
            'edit' => Pages\EditContractRenewal::route('/{record}/edit'),
        ];
    }
}
