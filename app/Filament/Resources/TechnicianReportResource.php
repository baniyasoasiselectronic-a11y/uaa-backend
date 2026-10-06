<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TechnicianReportResource\Pages;
use App\Models\TechnicianReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TechnicianReportResource extends Resource
{
    protected static ?string $model = TechnicianReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Customer Care';

    protected static ?string $navigationLabel = 'Technician Reports';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Technician')->columns(3)->schema([
                Forms\Components\TextInput::make('technician_name')->label('Name')->required()->maxLength(255),
                Forms\Components\TextInput::make('technician_code')->label('ID')->required()->maxLength(100),
                Forms\Components\DatePicker::make('report_date')->label('Date')->default(now())->required(),
            ]),
            Forms\Components\Section::make('Complaints attended')->schema([
                Forms\Components\Repeater::make('entries')
                    ->hiddenLabel()
                    ->addActionLabel('Add complaint')
                    ->required()
                    ->minItems(1)
                    ->columns(6)
                    ->itemLabel(fn (array $state) => $state['complain_no'] ? 'Complaint '.$state['complain_no'] : null)
                    ->schema([
                        Forms\Components\TextInput::make('complain_no')->label('Complain no.')->required()->maxLength(100),
                        Forms\Components\TextInput::make('spare_parts')->label('Spare parts')->maxLength(255)->columnSpan(2),
                        Forms\Components\Select::make('status')->options(['Done' => 'Done', 'Pending' => 'Pending', 'In progress' => 'In progress'])->required(),
                        Forms\Components\TimePicker::make('time_in')->label('Time in')->seconds(false),
                        Forms\Components\TimePicker::make('time_out')->label('Time out')->seconds(false),
                        Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('report_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('report_date')->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('technician_name')->label('Technician')->searchable(),
                Tables\Columns\TextColumn::make('technician_code')->label('ID')->searchable(),
                Tables\Columns\TextColumn::make('entries')->label('Complaints')->state(fn (TechnicianReport $r) => count((array) $r->entries)),
                Tables\Columns\IconColumn::make('imported')->boolean()->label('Old record')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechnicianReports::route('/'),
            'create' => Pages\CreateTechnicianReport::route('/create'),
            'edit' => Pages\EditTechnicianReport::route('/{record}/edit'),
        ];
    }
}
