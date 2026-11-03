<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant scope from the signed-in user. Super admins bypass scoping
 * (they see every tenant); everyone else is pinned to their own tenant.
 */
class ResolveCurrentTenant
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isSuperAdmin()) {
            $this->tenant->bypass();
        } else {
            $this->tenant->set($user?->tenant_id);
        }

        return $next($request);
    }
}
