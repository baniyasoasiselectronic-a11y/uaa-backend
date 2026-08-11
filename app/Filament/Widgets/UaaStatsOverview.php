<?php

namespace App\Filament\Widgets;

use App\Models\Career;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\JobApplication;
use App\Models\Lead;
use App\Models\OpenHouse;
use App\Models\Property;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UaaStatsOverview extends BaseWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        $newEnquiries = Enquiry::where('status', 'new')->count();
        $openComplaints = Complaint::whereNotIn('status', ['resolved', 'closed'])->count();
        $activeLeads = Lead::whereNotIn('stage', ['converted', 'lost'])->count();

        return [
            Stat::make('Properties', Property::count())
                ->description(Property::where('is_published', true)->count().' published')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Available units', Unit::where('status', 'available')->count())
                ->description(Unit::count().' units total')
                ->descriptionIcon('heroicon-m-key')
                ->color('success'),

            Stat::make('Active leads', $activeLeads)
                ->description($newEnquiries.' new enquiries')
                ->descriptionIcon('heroicon-m-funnel')
                ->color('warning'),

            Stat::make('Open complaints', $openComplaints)
                ->description(Complaint::count().' tickets total')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($openComplaints > 0 ? 'danger' : 'success'),

            Stat::make('Open houses', OpenHouse::where('is_published', true)->count())
                ->description('Published events')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Job applications', JobApplication::where('status', 'new')->count())
                ->description(Career::where('is_open', true)->count().' open roles')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('gray'),
        ];
    }
}
