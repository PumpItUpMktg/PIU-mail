<?php

namespace App\Enums;

enum Role: string
{
    /** PIU staff. Sees every tenant. Has no tenant of their own. */
    case SuperAdmin = 'super_admin';

    /** Office / marketing within a tenant: approves Target Areas and sends. */
    case Office = 'office';

    /** Field tech within a tenant: creates Target Areas and enters notes. */
    case Tech = 'tech';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Office => 'Office / Marketing',
            self::Tech => 'Tech',
        };
    }

    public function isTenantBound(): bool
    {
        return $this !== self::SuperAdmin;
    }

    public function canCreateTargetAreas(): bool
    {
        return true;
    }

    public function canApproveTargetAreas(): bool
    {
        return $this !== self::Tech;
    }

    public function canSendMailings(): bool
    {
        return $this !== self::Tech;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
