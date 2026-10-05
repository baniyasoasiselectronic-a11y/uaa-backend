<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Support\UploadsToCloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Architectural floor plans shown in the "Floor plans" section of the public
 * project page (project.html). One row per plan so each gets its own name
 * and specs (area / bedrooms) next to the drawing.
 */
class FloorPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'floorPlans';

    protected static ?string $title = 'Floor Plans';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Plan name')
                ->placeholder('e.g. Typical Floor, Ground Floor, 2 Bedroom Type A')
                ->required()
                ->maxLength(255),
            UploadsToCloudinary::apply(
                Forms\Components\FileUpload::make('path')
                    ->label('Floor plan image')
                    ->helperText('PNG or JPG of the drawing. Visitors can open it full-screen and zoom.')
                    ->image()
                    ->imagePreviewHeight('160')
                    ->required(),
                'floor-plans'
            ),
            Forms\Components\TextInput::make('area')
                ->label('Area (optional)')
                ->placeholder('e.g. 1,150 sq ft')
                ->maxLength(100),
            Forms\Components\TextInput::make('bedrooms')
                ->label('Bedrooms / type (optional)')
                ->placeholder('e.g. 2 BR, Studio, Retail')
                ->maxLength(100),
            Forms\Components\Textarea::make('notes')
                ->label('Notes (optional)')
                ->rows(2),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers show first. You can also drag rows to reorder.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('path')->label('Plan')->height(56),
                Tables\Columns\TextColumn::make('title')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('bedrooms')->label('Type'),
                Tables\Columns\TextColumn::make('area')->label('Area'),
                Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New floor plan')
                    ->modalHeading('Add floor plan'),
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
