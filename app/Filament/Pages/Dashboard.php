<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    public function getColumns(): int|string|array
    {
        return ['default' => 1, 'lg' => 2];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
