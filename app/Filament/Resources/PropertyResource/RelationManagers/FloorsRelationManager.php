<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Models\PropertyFloor;
use App\Support\UploadsToCloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Floor-by-floor explorer data for the public project page: each floor has
 * its overview plan and the apartment types on it (1/2/3 BHK, areas, plan).
 */
class FloorsRelationManager extends RelationManager
{
    protected static string $relationship = 'floors';

    protected static ?string $title = 'Floors & Units';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->label('Floor name')
                ->placeholder('e.g. 1st Floor, 7th Floor')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('sort_order')
                ->label('Floor order')
                ->numeric()
                ->default(0)
                ->helperText('Lowest floor first: 1st floor = 1, 2nd = 2, 3rd = 3 …'),
            UploadsToCloudinary::apply(
                Forms\Components\FileUpload::make('plan_image')
                    ->label('Floor overview plan')
                    ->helperText('The whole-floor drawing (the coloured one showing every apartment).')
                    ->image()
                    ->imagePreviewHeight('140'),
                'floors',
            ),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('total_apartments')
                    ->label('Apartments on this floor')
                    ->numeric()
                    ->helperText('Leave blank to count the apartment types below.'),
                Forms\Components\TextInput::make('total_area_sqm')
                    ->label('Total floor area (sq m)')
                    ->numeric()
                    ->helperText('Leave blank to add up the apartments below.'),
            ]),
            Forms\Components\Textarea::make('notes')
                ->label('Notes (optional)')
                ->rows(2),
            Forms\Components\Repeater::make('units')
                ->label('Apartment types on this floor')
                ->relationship()
                ->orderColumn('sort_order')
                ->collapsible()
                ->cloneable()
                ->defaultItems(0)
                ->addActionLabel('Add apartment type')
                ->itemLabel(fn (array $state): ?string => filled($state['unit_type'] ?? null)
                    ? $state['unit_type'].(isset($state['bedrooms']) && $state['bedrooms'] !== '' && $state['bedrooms'] !== null
                        ? ' — '.(((int) $state['bedrooms']) === 0 ? 'Studio' : ((int) $state['bedrooms']).' BHK')
                        : '')
                    : null)
                ->schema([
                    Forms\Components\TextInput::make('unit_type')
                        ->label('Type code')
                        ->placeholder('e.g. 2B03')
                        ->required()
                        ->maxLength(50),
                    Forms\Components\Select::make('bedrooms')
                        ->label('Category')
                        ->options([0 => 'Studio', 1 => '1 BHK', 2 => '2 BHK', 3 => '3 BHK', 4 => '4 BHK', 5 => '5 BHK', 6 => '6 BHK'])
                        ->required(),
                    Forms\Components\TextInput::make('suite_sqm')
                        ->label('Suite area (sq m)')
                        ->numeric(),
                    Forms\Components\Select::make('outdoor_label')
                        ->label('Outdoor space')
                        ->options(['Balcony' => 'Balcony', 'Terrace' => 'Terrace', 'Garden' => 'Garden', 'Roof terrace' => 'Roof terrace'])
                        ->default('Balcony')
                        ->required(),
                    Forms\Components\TextInput::make('outdoor_sqm')
                        ->label('Balcony / terrace area (sq m)')
                        ->numeric()
                        ->helperText('Total area is added up automatically; sq ft is converted on the site.'),
                    UploadsToCloudinary::apply(
                        Forms\Components\FileUpload::make('image')
                            ->label('Apartment plan')
                            ->image()
                            ->imagePreviewHeight('120'),
                        'floors',
                    ),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('plan_image')->label('Overview')->height(56),
                Tables\Columns\TextColumn::make('label')->label('Floor')->searchable(),
                Tables\Columns\TextColumn::make('units_count')->counts('units')->label('Apartment types'),
                Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New floor')
                    ->modalHeading('Add floor')
                    ->modalWidth('5xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('5xl'),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->modalDescription('Copies this floor and all its apartment types (they share the same plan images). Handy for identical floors.')
                    ->action(function (PropertyFloor $record) {
                        $copy = $record->replicate();
                        $copy->label = $record->label.' (copy)';
                        $copy->sort_order = (int) $record->property->floors()->max('sort_order') + 1;
                        $copy->save();
                        foreach ($record->units as $unit) {
                            $copy->units()->create($unit->only([
                                'unit_type', 'bedrooms', 'suite_sqm', 'outdoor_label', 'outdoor_sqm', 'image', 'notes', 'sort_order',
                            ]));
                        }
                        Notification::make()->title('Floor duplicated — rename the copy')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
