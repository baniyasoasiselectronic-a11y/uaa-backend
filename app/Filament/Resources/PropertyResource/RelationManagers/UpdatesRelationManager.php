<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Models\ProjectUpdate;
use App\Support\UploadsToCloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Month-by-month construction progress feed for off-plan / coming-soon
 * properties (see project.html on the public site).
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Construction Progress Updates';

    /**
     * Metadata-only schema — shared default, and what EditAction uses. Photos
     * are deliberately not part of this: editing a record must never demand
     * a fresh photo upload just to fix a typo in the title.
     */
    public function form(Form $form): Form
    {
        return $form->schema(static::metadataFields());
    }

    protected static function metadataFields(): array
    {
        return [
            Forms\Components\TextInput::make('title')
                ->label('Update Title')
                ->placeholder('Construction Progress Update')
                ->helperText('Leave blank to use the default "Construction Progress Update".')
                ->maxLength(255),
            Forms\Components\DatePicker::make('period_date')
                ->label('Month')
                ->displayFormat('F Y')
                ->native(false)
                ->closeOnDateSelection()
                ->required()
                ->default(now()->startOfMonth()),
            Forms\Components\Textarea::make('notes')
                ->label('Notes (optional)')
                ->rows(2),
        ];
    }

    protected static function photosField(string $label = 'Photos'): Forms\Components\FileUpload
    {
        return UploadsToCloudinary::apply(
            Forms\Components\FileUpload::make('images')
                ->label($label)
                ->helperText('Select all photos for this month\'s update.')
                ->image()
                ->multiple()
                ->reorderable()
                ->appendFiles()
                ->maxFiles(40)
                ->imagePreviewHeight('110')
                ->required(),
            'project-update-images'
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('period_date', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')->label('Preview'),
                Tables\Columns\TextColumn::make('period_date')->label('Month')->date('F Y')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Title')->default('Construction Progress Update'),
                Tables\Columns\TextColumn::make('images_count')->counts('images')->label('Photos'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New Monthly Update')
                    ->form([...static::metadataFields(), static::photosField()])
                    ->using(function (array $data, RelationManager $livewire) {
                        $owner = $livewire->getOwnerRecord();
                        $paths = UploadsToCloudinary::normalizePaths($data['images'] ?? []);

                        if (! $paths) {
                            Notification::make()
                                ->title('No photos were saved')
                                ->body('Every upload in this batch failed — see the error above.')
                                ->danger()
                                ->send();

                            throw new Halt();
                        }

                        $update = $owner->updates()->create([
                            'title' => $data['title'] ?: null,
                            'period_date' => $data['period_date'],
                            'notes' => $data['notes'] ?? null,
                        ]);

                        foreach ($paths as $i => $path) {
                            $update->images()->create(['path' => $path, 'sort_order' => $i]);
                        }

                        return $update;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('addPhotos')
                    ->label('Add Photos')
                    ->icon('heroicon-o-photo')
                    ->form([static::photosField('Additional Photos')])
                    ->action(function (array $data, ProjectUpdate $record) {
                        $paths = UploadsToCloudinary::normalizePaths($data['images'] ?? []);
                        if (! $paths) {
                            Notification::make()->title('No photos were saved')->danger()->send();

                            return;
                        }
                        $base = (int) $record->images()->max('sort_order');
                        foreach ($paths as $i => $path) {
                            $record->images()->create(['path' => $path, 'sort_order' => $base + $i + 1]);
                        }
                        Notification::make()->title('Photos added')->success()->send();
                    }),
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
