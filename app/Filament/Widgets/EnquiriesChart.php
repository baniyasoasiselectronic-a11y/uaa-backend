<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EnquiryResource;
use App\Models\Enquiry;
use Filament\Widgets\ChartWidget;

/** Enquiries per day for the last 30 days, split by where they came from. */
class EnquiriesChart extends ChartWidget
{
    protected static ?string $heading = 'Enquiries — last 30 days';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());
        $rows = Enquiry::query()->where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as d, source, COUNT(*) as c')->groupBy('d', 'source')->get();

        $palette = ['website' => '#C9A227', 'ai_assistant' => '#5B8DEF', 'whatsapp_click' => '#2FB8A6', 'call_click' => '#E0736B', 'ai_call' => '#9a8cff'];
        $datasets = [];
        foreach (EnquiryResource::SOURCES as $key => $label) {
            $color = $palette[$key] ?? '#8A8577';
            $datasets[] = [
                'label' => $label,
                'data' => $days->map(fn ($d) => (int) ($rows->first(fn ($r) => (string) $r->d === $d->toDateString() && $r->source === $key)->c ?? 0))->all(),
                'borderColor' => $color,
                'backgroundColor' => $color.'22',
                'tension' => .38,
                'fill' => true,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                'borderWidth' => 2.2,
            ];
        }

        return ['datasets' => $datasets, 'labels' => $days->map(fn ($d) => $d->format('d M'))->all()];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]]],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 8]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => 'rgba(201,162,39,.08)']],
            ],
        ];
    }
}
