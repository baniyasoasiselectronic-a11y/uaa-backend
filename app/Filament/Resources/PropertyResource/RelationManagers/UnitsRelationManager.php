<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Available units';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('unit_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('bedrooms')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('bathrooms')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('area_sqft')
                    ->label('Area (sq ft)')
                    ->numeric(),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('AED'),
                Forms\Components\Select::make('status')
                    ->options([
                        'available' => 'Available',
                        'reserved' => 'Reserved',
                        'sold' => 'Sold',
                        'rented' => 'Rented',
                    ])
                    ->default('available')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit_number')
            ->columns([
                Tables\Columns\TextColumn::make('unit_number')->searchable()->label('Unit'),
                Tables\Columns\TextColumn::make('bedrooms')->sortable()->label('Beds'),
                Tables\Columns\TextColumn::make('bathrooms')->sortable()->label('Baths'),
                Tables\Columns\TextColumn::make('area_sqft')->numeric()->sortable()->label('Area'),
                Tables\Columns\TextColumn::make('price')->money('AED')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'reserved' => 'warning',
                        'sold', 'rented' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'reserved' => 'Reserved',
                        'sold' => 'Sold',
                        'rented' => 'Rented',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
