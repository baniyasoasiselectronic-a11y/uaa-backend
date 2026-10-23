<?php

namespace App\Filament\Concerns;

/**
 * For dashboard widgets that show anything other than enquiries (complaints, payments, move reports, stats across
 * the business): an enquiries-only account never sees them. Everyone else sees them exactly as before.
 */
trait HiddenFromEnquiriesOnly
{
    public static function canView(): bool
    {
        return ! (auth()->user()?->isEnquiriesOnly() ?? false);
    }
}
