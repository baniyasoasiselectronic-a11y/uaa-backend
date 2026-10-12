<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EnquiryResource\Pages;
use App\Filament\Resources\EnquiryResource\RelationManagers;
use App\Models\Enquiry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    public const SOURCES = [
        'website' => 'Website form',
        'ai_assistant' => 'AI assistant chat',
        'whatsapp_click' => 'WhatsApp icon',
        'call_click' => 'Call icon',
        'ai_call' => 'AI phone call',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    public static function getNavigationBadge(): ?string
    {
        $n = \App\Models\Enquiry::where('status', 'new')->count();

        return $n ? (string) $n : null;
    }

    protected static ?string $navigationGroup = 'CRM & Leads';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('property_id')
                    ->relationship('property', 'title'),
                Forms\Components\Select::make('unit_id')
                    ->relationship('unit', 'id'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('preferred_unit')
                    ->maxLength(255),
                Forms\Components\Textarea::make('message')
                    ->columnSpanFull(),
                Forms\Components\Select::make('source')
                    ->options(self::SOURCES)
                    ->required()
                    ->default('website'),
                Forms\Components\TextInput::make('page')->label('Page / building')->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options(['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'])
                    ->required()
                    ->default('new'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Received')->dateTime('d M Y, H:i')->since()->sortable()
                    ->tooltip(fn ($record) => $record->created_at?->format('d M Y, H:i')),
                Tables\Columns\TextColumn::make('source')->label('Came from')->badge()
                    ->formatStateUsing(fn (?string $state) => self::SOURCES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'website' => 'primary', 'ai_assistant' => 'info', 'whatsapp_click' => 'success',
                        'call_click' => 'warning', 'ai_call' => 'gray', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('email')->searchable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('message')->limit(70)->wrap()->tooltip(fn ($record) => $record->message),
                Tables\Columns\TextColumn::make('property.title')->label('Property')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('page')->label('Page')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) { 'new' => 'danger', 'contacted' => 'warning', default => 'success' }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')->label('Came from')->options(self::SOURCES),
                Tables\Filters\SelectFilter::make('status')->options(['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed']),
            ])
            ->actions([
                Tables\Actions\Action::make('contacted')->label('Mark contacted')->icon('heroicon-o-check')
                    ->visible(fn ($record) => $record->status === 'new')
                    ->action(fn ($record) => $record->update(['status' => 'contacted'])),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnquiries::route('/'),
            'create' => Pages\CreateEnquiry::route('/create'),
            'edit' => Pages\EditEnquiry::route('/{record}/edit'),
        ];
    }
}

