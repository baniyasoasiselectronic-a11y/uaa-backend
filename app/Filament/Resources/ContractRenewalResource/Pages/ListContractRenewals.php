<?php

namespace App\Filament\Resources\ContractRenewalResource\Pages;

use App\Filament\Resources\ContractRenewalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContractRenewals extends ListRecords
{
    protected static string $resource = ContractRenewalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
