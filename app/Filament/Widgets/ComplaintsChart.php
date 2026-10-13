<?php

namespace App\Filament\Widgets;

use App\Models\Complaint;
use Filament\Widgets\ChartWidget;

/** Where every complaint stands right now. */
class ComplaintsChart extends ChartWidget
{
    protected static ?string $heading = 'Complaints by status';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '270px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $labels = ['new' => 'New', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
        $counts = Complaint::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'labels' => array_values($labels),
            'datasets' => [[
                'data' => array_map(fn ($k) => (int) ($counts[$k] ?? 0), array_keys($labels)),
                'backgroundColor' => ['#E0736B', '#5B8DEF', '#C9A227', '#2FB8A6', '#6b665a'],
                'borderColor' => '#12110d',
                'borderWidth' => 3,
                'hoverOffset' => 8,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '66%',
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]]],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
