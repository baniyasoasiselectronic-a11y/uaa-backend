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
    use \App\Filament\Concerns\RestrictedByRole;

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
                Forms\Components\TextInput::make('total_apartments')
                    ->label('Total apartments')
                    ->numeric()
                    ->helperText('Shown at the top of the floor plans section on the building page.'),
                Forms\Components\TextInput::make('built_up_sqm')
                    ->label('Built-up area (sq m)')
                    ->numeric()
                    ->helperText('Square feet are converted automatically on the site.'),
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

    /** Cover photo URL: the main image, else the first gallery photo (links from the old site pass through). */
    public static function coverUrl(Building $b): ?string
    {
        $path = $b->main_image ?: $b->images->first()?->path;
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : \Illuminate\Support\Facades\Storage::url($path);
    }

    /** Buildings shown as a modern card gallery: cover photo, name, area, price, units and quick actions. */
    public static function table(Table $table): Table
    {
        $placeholder = 'data:image/svg+xml;utf8,'.rawurlencode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2a2414"/><stop offset="1" stop-color="#0e0d09"/></linearGradient></defs><rect width="640" height="400" fill="url(#g)"/><g fill="none" stroke="#C9A227" stroke-width="3" opacity=".75"><path d="M250 300V120l70-28 70 28v180"/><path d="M290 300v-60h60v60M278 150h24M338 150h24M278 195h24M338 195h24"/></g></svg>'
        );

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['community', 'images']))
            ->defaultSort('name')
            ->contentGrid(['md' => 2, 'xl' => 3, '2xl' => 4])
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(12)
            ->recordUrl(fn (Building $r) => static::getUrl('edit', ['record' => $r]))
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\ImageColumn::make('cover')
                        ->state(fn (Building $r) => static::coverUrl($r))
                        ->defaultImageUrl($placeholder)
                        ->width('100%')->height('12.5rem')->square(false)
                        ->extraImgAttributes(['class' => 'uaa-cover', 'loading' => 'lazy', 'onerror' => "this.style.opacity=0"]),
                    Tables\Columns\TextColumn::make('name')->searchable()->sortable()
                        ->weight('bold')->size('lg')->extraAttributes(['class' => 'uaa-card-title']),
                    Tables\Columns\TextColumn::make('community.name')->badge()->color('primary')
                        ->icon('heroicon-m-map-pin')->placeholder('No area set'),
                    Tables\Columns\TextColumn::make('average_price')
                        ->state(fn (Building $r) => $r->average_price ? 'AED '.number_format((float) $r->average_price).' / year' : 'Price not set')
                        ->extraAttributes(['class' => 'uaa-card-price']),
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('units_free')->icon('heroicon-m-key')->color('gray')
                            ->state(fn (Building $r) => $r->units()->where('status', 'available')->count().' / '.$r->units()->count().' free')->tooltip('Available units / total units'),
                        Tables\Columns\TextColumn::make('floors_count')->icon('heroicon-m-bars-3-bottom-left')->color('gray')
                            ->state(fn (Building $r) => $r->floors_count ? $r->floors_count.' floors' : '—'),
                        Tables\Columns\TextColumn::make('year_built')->icon('heroicon-m-calendar')->color('gray')
                            ->state(fn (Building $r) => $r->year_built ?: '—'),
                    ])->extraAttributes(['class' => 'uaa-card-stats']),
                ])->space(3)->extraAttributes(['class' => 'uaa-building-card']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('community_id')->label('Area')->relationship('community', 'name')->searchable()->preload(),
                Tables\Filters\Filter::make('no_map')->label('Missing a map pin')->query(fn (Builder $q) => $q->whereNull('latitude')),
                Tables\Filters\Filter::make('no_photo')->label('Missing photos')
                    ->query(fn (Builder $q) => $q->whereNull('main_image')->whereDoesntHave('images')),
            ])
            ->actions([
                Tables\Actions\Action::make('map')->label('Map')->icon('heroicon-o-map-pin')->color('gray')
                    ->visible(fn (Building $r) => $r->latitude !== null)
                    ->url(fn (Building $r) => 'https://www.google.com/maps?q='.$r->latitude.','.$r->longitude, shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()->label('Edit'),
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
            RelationManagers\FloorsRelationManager::class,
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

