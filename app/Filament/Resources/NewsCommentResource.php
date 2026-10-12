<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsCommentResource\Pages;
use App\Models\NewsComment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Comments visitors leave on blog articles (like the old site's Comments screen).
 * New comments are "Pending" and only appear on the website once approved here.
 */
class NewsCommentResource extends Resource
{
    protected static ?string $model = NewsComment::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Comments';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $n = NewsComment::where('status', 'pending')->count();

        return $n ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Received')->since()->sortable()
                    ->tooltip(fn (NewsComment $r) => $r->created_at?->format('d M Y, H:i')),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('comment')->limit(120)->wrap()->searchable()->tooltip(fn (NewsComment $r) => $r->comment),
                Tables\Columns\TextColumn::make('news.title')->label('Article')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $s) => ['pending' => 'Pending', 'approved' => 'Approved', 'spam' => 'Spam'][$s] ?? $s)
                    ->color(fn (string $s) => match ($s) { 'approved' => 'success', 'spam' => 'danger', default => 'warning' }),
                Tables\Columns\TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'spam' => 'Spam'])->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')->label('Approve')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (NewsComment $r) => $r->status !== 'approved')
                    ->action(fn (NewsComment $r) => $r->update(['status' => 'approved'])),
                Tables\Actions\Action::make('spam')->label('Spam')->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(fn (NewsComment $r) => $r->status !== 'spam')
                    ->action(fn (NewsComment $r) => $r->update(['status' => 'spam'])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')->label('Approve selected')->icon('heroicon-o-check')->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'approved']))->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('spam')->label('Mark as spam')->icon('heroicon-o-no-symbol')->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'spam']))->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsComments::route('/'),
        ];
    }
}
