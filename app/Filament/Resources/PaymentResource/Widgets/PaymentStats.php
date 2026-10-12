<?php

namespace App\Filament\Resources\PaymentResource\Widgets;

use App\Models\Complaint;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStats extends BaseWidget
{
    protected function getStats(): array
    {
        $paid = Complaint::where('payment_status', 'paid');

        return [
            Stat::make('Paid complaints', number_format((clone $paid)->count()))
                ->description('AED '.number_format((float) (clone $paid)->sum('payment_amount')).' recorded')
                ->descriptionIcon('heroicon-m-banknotes')->color('success'),
            Stat::make('Unpaid', number_format(Complaint::where('payment_status', 'unpaid')->count()))
                ->description('waiting for payment')->color('danger'),
            Stat::make('Free (no-fee tenants)', number_format(Complaint::where('payment_status', 'free')->count()))
                ->description('old Administrator-role tenants')->color('info'),
            Stat::make('Gateway', 'Not connected')
                ->description('Stripe is switched off')->descriptionIcon('heroicon-m-lock-closed')->color('gray'),
        ];
    }
}
