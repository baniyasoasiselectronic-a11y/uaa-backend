<?php

namespace App\Filament\Support;

use App\Support\UaaOracle;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Property / unit / unit-type fields shared by the inspection-style forms:
 * dropdowns fed by the Oracle API when it is reachable, plain typed fields otherwise.
 */
class UnitPicker
{
    public static function components(): array
    {
        $oracle = UaaOracle::available();
        $types = $oracle && UaaOracle::unitTypes() ? UaaOracle::unitTypes() : array_combine($t = ['Apartment', 'Studio', 'Villa', 'Shop', 'Office', 'Other'], $t);

        return [
            Forms\Components\Select::make('oracle_property_id')
                ->label('Property')
                ->options(fn () => UaaOracle::properties())
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $set('property_name', UaaOracle::properties()[$state] ?? null);
                    $set('unit_label', null);
                    $set('oracle_unit_id', null);
                })
                ->visible($oracle),
            Forms\Components\Hidden::make('property_name')->visible($oracle),
            Forms\Components\Hidden::make('oracle_unit_id'),
            Forms\Components\Select::make('unit_label')
                ->label('Unit number')
                ->options(fn (Get $get) => UaaOracle::units($get('oracle_property_id')))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn ($state, Get $get, Set $set) => $set('oracle_unit_id', UaaOracle::unitId($get('oracle_property_id'), $state)))
                ->visible($oracle),
            Forms\Components\TextInput::make('property_name')->label('Property')->maxLength(255)->visible(! $oracle),
            Forms\Components\TextInput::make('unit_label')->label('Unit number')->maxLength(100)->required()->visible(! $oracle),
            Forms\Components\Select::make('unit_type')->options($types),
        ];
    }
}
