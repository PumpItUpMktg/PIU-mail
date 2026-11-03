<?php

namespace App\Models\Scopes;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $current = app(CurrentTenant::class);

        if ($current->isBypassed()) {
            return;
        }

        $column = $model->qualifyColumn('tenant_id');

        if ($current->id() === null) {
            // Fail closed: no tenant resolved, no rows.
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($column, $current->id());
    }
}
