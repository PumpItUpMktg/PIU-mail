<?php

namespace App\Support;

/**
 * Request-lifetime holder for the active tenant.
 *
 * Fail-closed: when no tenant is set and bypass is off, tenant-scoped queries
 * return nothing. Super admins (and console/queue code that is deliberately
 * cross-tenant) must opt in with bypass().
 */
final class CurrentTenant
{
    private ?string $tenantId = null;

    private bool $bypass = false;

    public function set(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->bypass = false;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function bypass(bool $bypass = true): void
    {
        $this->bypass = $bypass;
    }

    public function isBypassed(): bool
    {
        return $this->bypass;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->bypass = false;
    }

    /**
     * Run a callback with scoping bypassed, restoring prior state afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutScope(callable $callback): mixed
    {
        [$id, $bypass] = [$this->tenantId, $this->bypass];
        $this->bypass = true;

        try {
            return $callback();
        } finally {
            [$this->tenantId, $this->bypass] = [$id, $bypass];
        }
    }
}
