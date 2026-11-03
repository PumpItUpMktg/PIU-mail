<?php

namespace App\Filament\Concerns;

/**
 * Restricts a Filament resource/page to super admins.
 */
trait SuperAdminOnly
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }
}
