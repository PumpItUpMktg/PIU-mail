<?php

namespace Database\Seeders;

use App\Enums\CountyStatus;
use App\Models\County;
use Illuminate\Database\Seeder;

/** Seeds all 21 NJ counties into the import registry (idempotent). */
class CountySeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('piu.nj_counties') as $name => $fips) {
            County::firstOrCreate(['name' => $name], ['fips' => $fips, 'status' => CountyStatus::NotLoaded]);
        }
    }
}
