<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountySeeder::class,
            TenantSeeder::class,
        ]);
        // Super admins are created with `php artisan piu:create-super-admin`
        // so no credentials live in the repo.
    }
}
