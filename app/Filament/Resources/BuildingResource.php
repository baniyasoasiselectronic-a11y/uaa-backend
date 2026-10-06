<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuildingResource\Pages;
use App\Filament\Resources\BuildingResource\RelationManagers;
use App\Models\Building;
use App\Support\UploadsToCloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BuildingResource extends Resource
{
    protected static ?string $model = Building::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Properties';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('property_id')
                    ->relationship('property', 'title'),
                Forms\Components\Select::make('community_id')
                    ->relationship('community', 'name'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('floors_count')
                    ->numeric(),
                Forms\Components\TextInput::make('year_built'),
                Forms\Components\Section::make('Location on Google Maps')
                    ->description('Shown as an exact pin on the building page. Open the building in Google Maps, press Share, copy the link and paste it below — the coordinates are filled in automatically when you save.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Address (shown under the map)')
                            ->placeholder('e.g. Baniyas Road, Deira, Dubai')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('map_url')
                            ->label('Google Maps link')
                            ->placeholder('https://maps.app.goo.gl/…  or  https://www.google.com/maps/place/…')
                            ->maxLength(600)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                if ($value && ! \App\Support\GoogleMapsLink::coordinates($value)) {
                                    $fail('Could not read a location from this link. Paste the Share link of the pin, or enter the latitude and longitude below.');
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()->minValue(-90)->maxValue(90)
                            ->helperText('Filled from the link. Or type it: in Google Maps right-click the pin and click the numbers to copy.'),
                        Forms\Components\TextInput::make('longitude')
                            ->numeric()->minValue(-180)->maxValue(180),
                    ]),
                Forms\Components\TextInput::make('average_price')
                    ->label('Average price / budget')
                    ->numeric()
                    ->prefix('AED')
                    ->helperText('Shown publicly. Leave blank to auto-use the average of the units.'),
                UploadsToCloudinary::apply(
                    Forms\Components\FileUpload::make('main_image')
                        ->label('Main image')
                        ->image(),
                    'building-images'
                ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('property.title')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('community.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('floors_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('year_built'),
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
                //
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
            RelationManagers\ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuildings::route('/'),
            'create' => Pages\CreateBuilding::route('/create'),
            'edit' => Pages\EditBuilding::route('/{record}/edit'),
        ];
    }
}

