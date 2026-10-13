<?php

namespace App\Filament\Widgets;

use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\JobApplication;
use App\Models\Lead;
use App\Models\MoveReport;
use App\Models\NewsComment;
use App\Models\Property;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UaaStatsOverview extends BaseWidget
{
    protected static ?int $sort = -5;

    protected function getColumns(): int
    {
        return 4;
    }

    /** Items created per day over the last 14 days — drives the little trend line on each card. */
    private function trend(string $model): array
    {
        $from = now()->subDays(13)->startOfDay();
        $rows = $model::query()->where('created_at', '>=', $from)->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');

        return collect(range(13, 0))->map(fn ($i) => (int) ($rows[now()->subDays($i)->toDateString()] ?? 0))->all();
    }

    protected function getStats(): array
    {
        $newEnquiries = Enquiry::where('status', 'new')->count();
        $openComplaints = Complaint::whereNotIn('status', ['resolved', 'closed'])->count();
        $monthMoves = MoveReport::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
        $activeLeads = Lead::whereNotIn('stage', ['converted', 'lost'])->count();

        return [
            Stat::make('Enquiries', number_format(Enquiry::count()))
                ->description($newEnquiries.' waiting for a reply')->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->chart($this->trend(Enquiry::class))->color($newEnquiries ? 'danger' : 'success'),

            Stat::make('Open complaints', number_format($openComplaints))
                ->description(number_format(Complaint::count()).' tickets in total')->descriptionIcon('heroicon-m-exclamation-triangle')
                ->chart($this->trend(Complaint::class))->color($openComplaints ? 'warning' : 'success'),

            Stat::make('Move in / out', number_format($monthMoves))
                ->description(MoveReport::whereDate('created_at', today())->count().' today · this month')->descriptionIcon('heroicon-m-clipboard-document-check')
                ->chart($this->trend(MoveReport::class))->color('success'),

            Stat::make('Active leads', number_format($activeLeads))
                ->description($newEnquiries.' new enquiries')->descriptionIcon('heroicon-m-funnel')
                ->chart($this->trend(Lead::class))->color('primary'),

            Stat::make('Available units', number_format(Unit::where('status', 'available')->count()))
                ->description(number_format(Unit::count()).' units in total')->descriptionIcon('heroicon-m-key')->color('success'),

            Stat::make('Properties', number_format(Property::count()))
                ->description(Property::where('is_published', true)->count().' published')->descriptionIcon('heroicon-m-building-office-2')->color('primary'),

            Stat::make('Comments to approve', number_format(NewsComment::where('status', 'pending')->count()))
                ->description('on blog articles')->descriptionIcon('heroicon-m-chat-bubble-left-right')->color('info'),

            Stat::make('Job applications', number_format(JobApplication::where('status', 'new')->count()))
                ->description('new, not yet reviewed')->descriptionIcon('heroicon-m-briefcase')->color('gray'),
        ];
    }
}
