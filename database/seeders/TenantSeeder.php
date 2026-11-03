<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** First tenant: Pump It Up Marketing and its three brands (idempotent). */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(['slug' => 'pump-it-up-marketing'], ['name' => 'Pump It Up Marketing']);

        app(CurrentTenant::class)->withoutScope(function () use ($tenant) {
            foreach (['Sump Pump Gurus', 'Trusted Mold Testing', 'ClearScale Water'] as $name) {
                Brand::firstOrCreate(['tenant_id' => $tenant->id, 'slug' => Str::slug($name)], ['name' => $name]);
            }
        });
    }
}
