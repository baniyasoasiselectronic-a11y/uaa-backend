<?php

namespace App\Filament\Widgets;

use App\Models\MoveReport;
use Filament\Widgets\ChartWidget;

/** Inspections submitted per week, split into Move In and Move Out (last 8 weeks). */
class MoveReportsChart extends ChartWidget
{
    protected static ?string $heading = 'Move in / out — last 8 weeks';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '270px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $weeks = collect(range(7, 0))->map(fn ($i) => now()->subWeeks($i)->startOfWeek());
        $reports = MoveReport::query()->where('created_at', '>=', $weeks->first())->get(['type', 'created_at']);

        $count = fn (string $type) => $weeks->map(fn ($w) => $reports->filter(
            fn ($r) => $r->type === $type && $r->created_at >= $w && $r->created_at < $w->copy()->addWeek()
        )->count())->all();

        return [
            'labels' => $weeks->map(fn ($w) => $w->format('d M'))->all(),
            'datasets' => [
                ['label' => 'Move In', 'data' => $count('move_in'), 'backgroundColor' => '#C9A227', 'borderRadius' => 8, 'borderSkipped' => false],
                ['label' => 'Move Out', 'data' => $count('move_out'), 'backgroundColor' => '#5B8DEF', 'borderRadius' => 8, 'borderSkipped' => false],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]]],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'stacked' => true],
                'y' => ['beginAtZero' => true, 'stacked' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => 'rgba(201,162,39,.08)']],
            ],
        ];
    }
}
