<?php

namespace App\Filament\Pages;

use App\Support\UaaOracle;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MoveInOutSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Customer Care';

    protected static ?string $navigationLabel = 'Move In/Out Settings';

    protected static ?string $title = 'Move In / Move Out settings';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.move-inout-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'oracle_api_url' => UaaOracle::setting(UaaOracle::URL_KEY),
            'inspectors' => UaaOracle::setting(UaaOracle::INSPECTORS_KEY),
            'renewal_staff' => UaaOracle::setting('renewal_staff'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Oracle API')->schema([
                    Forms\Components\TextInput::make('oracle_api_url')
                        ->label('Oracle API base URL')
                        ->url()
                        ->placeholder('https://…')
                        ->helperText('The buildings, units and unit types in the inspection form come from this address (same one the old UAA plugin used). Leave empty to type them by hand.'),
                ]),
                Forms\Components\Section::make('Inspector portal (PIN)')->schema([
                    Forms\Components\TextInput::make('portal_pin')
                        ->label('Inspection PIN')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->maxLength(40)
                        ->helperText(fn () => (UaaOracle::setting('move_portal_pin_hash') ? 'A PIN is set. Type a new one to change it; leave blank to keep it. ' : 'No PIN set yet — the page stays closed to inspectors until you set one. ').'Inspectors open ' . url('/inspection') . ' and enter this PIN. Signed-in admins can open it without a PIN.'),
                ]),
                Forms\Components\Section::make('Inspectors')->schema([
                    Forms\Components\Textarea::make('inspectors')
                        ->label('Inspector names')
                        ->rows(5)
                        ->helperText('One name per line. These appear in the Inspector list on every new inspection.'),
                    Forms\Components\Textarea::make('renewal_staff')
                        ->label('Contract renewal staff ("Renewed by")')
                        ->rows(3)
                        ->helperText('One name per line. Used in the Renewed by list on Contract Renewals.'),
                ]),
            ]);
    }

    public function save(): void
    {
        $d = $this->form->getState();
        UaaOracle::put(UaaOracle::URL_KEY, $d['oracle_api_url'] ?? null);
        UaaOracle::put(UaaOracle::INSPECTORS_KEY, $d['inspectors'] ?? null);
        UaaOracle::put('renewal_staff', $d['renewal_staff'] ?? null);
        if (filled($d['portal_pin'] ?? null)) {
            UaaOracle::put('move_portal_pin_hash', \Illuminate\Support\Facades\Hash::make($d['portal_pin']));
            $this->data['portal_pin'] = null;
        }

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test connection')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function () {
                    // Test what is typed in the form, even before it is saved.
                    $url = $this->data['oracle_api_url'] ?? null;
                    if ($url && $url !== UaaOracle::baseUrl()) {
                        UaaOracle::put(UaaOracle::URL_KEY, $url);
                    }
                    [$ok, $msg] = UaaOracle::test();
                    Notification::make()->title($ok ? 'Connected' : 'Connection failed')->body($msg)->color($ok ? 'success' : 'danger')->send();
                }),
        ];
    }
}
