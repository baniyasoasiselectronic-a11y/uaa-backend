<?php

namespace App\Filament\Resources\NewsCommentResource\Pages;

use App\Filament\Resources\NewsCommentResource;
use Filament\Resources\Pages\ListRecords;

class ListNewsComments extends ListRecords
{
    protected static string $resource = NewsCommentResource::class;

    protected ?string $subheading = 'Comments visitors leave on blog articles. They appear on the website only after you approve them.';
}
