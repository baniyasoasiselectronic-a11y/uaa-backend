<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\PaymentResource\Widgets\PaymentStats;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected ?string $subheading = 'Online payment is not connected yet — the Stripe gateway is switched off, so new complaints are free. Payments made on the old site are listed below.';

    protected function getHeaderWidgets(): array
    {
        return [PaymentStats::class];
    }
}
