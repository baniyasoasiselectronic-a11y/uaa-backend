<?php

namespace App\Filament\Resources\BuildingResource\RelationManagers;

use App\Support\UploadsToCloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Building images';

    public function form(Form $form): Form
    {
        return $form->schema([
            UploadsToCloudinary::apply(
                Forms\Components\FileUpload::make('path')
                    ->label('Image(s)')
                    ->helperText('Select multiple photos at once — each becomes its own gallery image.')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->maxFiles(20)
                    ->imagePreviewHeight('120')
                    ->required(),
                'building-images'
            ),
            Forms\Components\TextInput::make('alt')
                ->label('Alt text')
                ->helperText('Applied to every image uploaded above (edit a single image later to customise it).')
                ->maxLength(255),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0)->helperText('Starting order for the first image; extra images are numbered after it.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('path')->label('Image'),
                Tables\Columns\TextColumn::make('alt')->label('Alt text'),
                Tables\Columns\TextColumn::make('sort_order')->sortable()->label('Order'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New building image(s)')
                    ->modalHeading('Add building image(s)')
                    ->using(function (array $data, RelationManager $livewire) {
                        $owner = $livewire->getOwnerRecord();
                        // saveUploadedFileUsing() returns null for any image Cloudinary
                        // rejected (already surfaces its own notification) — drop those,
                        // keep whatever did upload successfully.
                        $paths = array_values(array_filter((array) ($data['path'] ?? [])));
                        $baseOrder = (int) ($data['sort_order'] ?? 0);

                        if (! $paths) {
                            Notification::make()
                                ->title('No images were saved')
                                ->body('Every upload in this batch failed — see the error above.')
                                ->danger()
                                ->send();

                            throw new Halt();
                        }

                        $record = null;
                        foreach ($paths as $i => $path) {
                            $record = $owner->images()->create([
                                'path' => $path,
                                'alt' => $data['alt'] ?? null,
                                'sort_order' => $baseOrder + $i,
                            ]);
                        }

                        return $record;
                    }),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
