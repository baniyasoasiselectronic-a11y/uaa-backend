<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\RelationManagers;
use App\Filament\Resources\UpcomingProjectResource\Pages;
use App\Models\Property;
use App\Support\UploadsToCloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A dedicated admin section for the homepage/site "Upcoming" projects
 * (off-plan and coming-soon listings). These are still Property records
 * under the hood — this resource just scopes the list to that subset and
 * gives it its own simplified form, so upcoming projects don't get lost
 * among regular for-rent properties.
 */
class UpcomingProjectResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationGroup = 'Properties';

    protected static ?string $navigationLabel = 'Upcoming Projects';

    protected static ?string $modelLabel = 'Upcoming Project';

    protected static ?string $pluralModelLabel = 'Upcoming Projects';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('status', ['off_plan', 'coming_soon']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('community_id')
                    ->relationship('community', 'name')
                    ->label('Community'),
                Forms\Components\Select::make('status')
                    ->options([
                        'off_plan' => 'Off-plan',
                        'coming_soon' => 'Coming soon',
                    ])
                    ->default('coming_soon')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('AED')
                    ->label('Starting price'),
                UploadsToCloudinary::apply(
                    Forms\Components\FileUpload::make('main_image')
                        ->label('Main image')
                        ->image(),
                    'property-images'
                ),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured'),
                Forms\Components\Toggle::make('is_published')
                    ->label('Published')
                    ->default(true),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('main_image')->label('Image'),
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('community.name')->label('Community'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('price')->money('AED')->label('Starting Price')->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured'),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Published'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
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
            RelationManagers\ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUpcomingProjects::route('/'),
            'create' => Pages\CreateUpcomingProject::route('/create'),
            'edit' => Pages\EditUpcomingProject::route('/{record}/edit'),
        ];
    }
}
