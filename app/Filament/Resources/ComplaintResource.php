<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplaintResource\Pages;
use App\Filament\Resources\ComplaintResource\RelationManagers;
use App\Models\Complaint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Customer Care';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ticket_number')
                    ->maxLength(255),
                Forms\Components\TextInput::make('complaint_number')->label('Complaint number (old format)')->maxLength(60),
                Forms\Components\TextInput::make('requisition_number')->label('Oracle requisition')->maxLength(60),
                Forms\Components\TextInput::make('property_name')->maxLength(255),
                Forms\Components\TextInput::make('unit_label')->label('Unit')->maxLength(60),
                Forms\Components\DatePicker::make('visit_date')->label('Preferred visit date'),
                Forms\Components\TextInput::make('visit_time_range')->label('Preferred visit time')->maxLength(60),
                Forms\Components\Select::make('payment_status')
                    ->options(['not_required' => 'Not required', 'unpaid' => 'Unpaid', 'paid' => 'Paid', 'free' => 'Free (no-fee tenant)'])
                    ->default('not_required')->required(),
                Forms\Components\TextInput::make('payment_amount')->numeric()->prefix('AED'),
                Forms\Components\TextInput::make('payment_ref')->label('Payment reference')->maxLength(255),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Select::make('property_id')
                    ->relationship('property', 'title'),
                Forms\Components\Select::make('unit_id')
                    ->relationship('unit', 'id'),
                Forms\Components\TextInput::make('category')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('priority')
                    ->required(),
                Forms\Components\TextInput::make('assigned_to')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('complaint_number')->label('Complaint no.')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('requisition_number')->label('Requisition')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('unit_label')->label('Unit')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('payment_status')->badge()
                    ->color(fn (string $state) => match ($state) { 'paid' => 'success', 'unpaid' => 'danger', 'free' => 'info', default => 'gray' }),
                Tables\Columns\TextColumn::make('customer.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('property.title')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('priority'),
                Tables\Columns\TextColumn::make('assigned_to')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options(['not_required' => 'Not required', 'unpaid' => 'Unpaid', 'paid' => 'Paid', 'free' => 'Free']),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['new' => 'New', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplaints::route('/'),
            'create' => Pages\CreateComplaint::route('/create'),
            'edit' => Pages\EditComplaint::route('/{record}/edit'),
        ];
    }
}

