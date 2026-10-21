<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may open an admin screen (a Filament resource).
 *
 *  - super_admin always can.
 *  - The Users screen (UserResource, also where accounts/roles are changed) is super_admin ONLY, always.
 *  - Every other screen: while config('staff_access.limit_screens') is off (default) all staff roles keep seeing it,
 *    exactly as before. When it is on (STAFF_SCREEN_LIMITS=true) a role sees only the screens listed for it in
 *    config/staff_access.php. A screen that is not listed there is super_admin only (safe default).
 *
 * The same answer is used for the menu entry, the list, create, edit, delete, view and restore, so typing the address of
 * a screen by hand does not open it either.
 */
trait RestrictedByRole
{
    protected static function staffMayUse(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $screen = class_basename(static::class);
        if ($screen === 'UserResource') {
            return false;
        }

        if (! config('staff_access.limit_screens', false)) {
            return $user->hasAnyRole(User::STAFF_ROLES);
        }

        $roles = (array) config('staff_access.resources.'.$screen, []);

        return $roles !== [] && $user->hasAnyRole($roles);
    }

    public static function canAccess(): bool
    {
        return static::staffMayUse();
    }

    public static function canViewAny(): bool
    {
        return static::staffMayUse();
    }

    public static function canView(Model $record): bool
    {
        return static::staffMayUse();
    }

    public static function canCreate(): bool
    {
        return static::staffMayUse();
    }

    public static function canEdit(Model $record): bool
    {
        return static::staffMayUse();
    }

    public static function canDelete(Model $record): bool
    {
        return static::staffMayUse();
    }

    public static function canDeleteAny(): bool
    {
        return static::staffMayUse();
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::staffMayUse();
    }

    public static function canForceDeleteAny(): bool
    {
        return static::staffMayUse();
    }

    public static function canRestore(Model $record): bool
    {
        return static::staffMayUse();
    }

    public static function canRestoreAny(): bool
    {
        return static::staffMayUse();
    }

    public static function canReorder(): bool
    {
        return static::staffMayUse();
    }

    public static function canReplicate(Model $record): bool
    {
        return static::staffMayUse();
    }
}
