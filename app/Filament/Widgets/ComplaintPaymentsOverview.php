<?php

namespace App\Filament\Widgets;

use App\Models\Complaint;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/** Dashboard row: complaint payments received (app + website) and whether the old site is still sending records. */
class ComplaintPaymentsOverview extends BaseWidget
{
    use \App\Filament\Concerns\HiddenFromEnquiriesOnly;

    protected static ?int $sort = -4;

    protected function getColumns(): int
    {
        return 4;
    }

    private function paid()
    {
        return Complaint::query()->where('payment_status', 'paid');
    }

    protected function getStats(): array
    {
        $today = $this->paid()->whereDate('created_at', today());
        $month = $this->paid()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        $appToday = Complaint::where('source', 'wordpress')->whereDate('created_at', today())->count();

        // the old site pings every few minutes; any sync call counts as "contact"
        $last = Cache::get('wp_sync:last_contact');
        if (! $last) {
            $sync = ['Not connected yet', 'The old site has not contacted this panel.', 'gray'];
        } else {
            $mins = (int) floor((time() - (int) $last) / 60);
            $ago = $mins < 1 ? 'just now' : ($mins < 60 ? $mins.' min ago' : round($mins / 60, 1).' h ago');
            $sync = $mins <= 30
                ? ['Connected', 'Old site / app records are arriving (last contact '.$ago.')', 'success']
                : ['No contact for '.$ago, 'Check Tools → UAA sync in WordPress', 'danger'];
        }

        return [
            Stat::make('Complaint payments today', 'AED '.number_format((float) $today->sum('payment_amount'), 2))
                ->description($today->count().' paid complaint(s)')->descriptionIcon('heroicon-m-banknotes')->color('success'),

            Stat::make('Payments this month', 'AED '.number_format((float) $month->sum('payment_amount'), 2))
                ->description($month->count().' paid complaint(s) · '.number_format($this->paid()->count()).' in total')->descriptionIcon('heroicon-m-credit-card')->color('success'),

            Stat::make('Complaints from the app today', number_format($appToday))
                ->description('arrive from the old site automatically')->descriptionIcon('heroicon-m-device-phone-mobile')->color('primary'),

            Stat::make('Old site sync', $sync[0])
                ->description($sync[1])->descriptionIcon('heroicon-m-arrow-path')->color($sync[2]),
        ];
    }
}
