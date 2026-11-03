<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A brand under a tenant (e.g. Sump Pump Gurus). Tenant-scoped.
 */
class Brand extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'logo_path', 'primary_color',
        'phone', 'website_url', 'return_address',
    ];
}
