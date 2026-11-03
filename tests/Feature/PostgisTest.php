<?php

use App\Models\County;

test('piu:check-postgis reports the PostGIS version', function () {
    $this->artisan('piu:check-postgis')
        ->expectsOutputToContain('POSTGIS=')
        ->assertSuccessful();
});

test('all 21 NJ counties are seeded', function () {
    $this->seed(\Database\Seeders\CountySeeder::class);

    expect(County::count())->toBe(21)
        ->and(County::where('name', 'MORRIS')->value('fips'))->toBe('34027')
        ->and(County::where('name', 'CAPE MAY')->exists())->toBeTrue();
});
