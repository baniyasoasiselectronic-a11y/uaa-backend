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
        'gravity_form' => 'Old site form (Gravity)',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    public static function getNavigationBadge(): ?string
    {
        $n = \App\Models\Enquiry::where('status', 'new')->count();

        return $n ? (string) $n : null;
    }

    protected static ?string $navigationGroup = 'CRM & Leads';

    protected static ?int $navigationSort = 2;

    /** WhatsApp number for an enquiry, in international form without "+" (UAE numbers get 971). Null if there is no usable phone. */
    public static function whatsappNumber(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '0') && in_array(strlen($d), [9, 10], true)) {
            $d = '971'.substr($d, 1);
        } elseif (strlen($d) === 9 && str_starts_with($d, '5')) {
            $d = '971'.$d;
        }

        return strlen($d) >= 10 && strlen($d) <= 15 ? $d : null;
    }

    /** Opens WhatsApp with a ready-to-send reply to this enquiry. */
    public static function whatsappUrl(\App\Models\Enquiry $e): ?string
    {
        $n = self::whatsappNumber($e->phone);
        if (! $n) {
            return null;
        }
        $about = $e->property?->title ?: ($e->building?->name ?? null);
        $first = trim(explode(' ', (string) $e->name)[0]);
        $text = 'Hello '.($first ?: 'there').', this is United Arab Agencies. Thank you for your enquiry'
            .($about ? ' about '.$about : '').'. How can we help you?';

        return 'https://wa.me/'.$n.'?text='.rawurlencode($text);
    }

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
                        'call_click' => 'warning', 'ai_call' => 'gray', 'gravity_form' => 'gray', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->placeholder('—')
                    ->icon(fn ($record) => self::whatsappUrl($record) ? 'heroicon-o-chat-bubble-left-ellipsis' : null)->iconColor('success')
                    ->url(fn ($record) => self::whatsappUrl($record), shouldOpenInNewTab: true),
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
                Tables\Actions\Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('success')
                    ->visible(fn ($record) => self::whatsappUrl($record) !== null)
                    ->url(fn ($record) => self::whatsappUrl($record), shouldOpenInNewTab: true),
                Tables\Actions\Action::make('call')->label('Call')->icon('heroicon-o-phone')->color('gray')
                    ->visible(fn ($record) => filled($record->phone))
                    ->url(fn ($record) => 'tel:'.preg_replace('/[^\d+]/', '', (string) $record->phone)),
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

