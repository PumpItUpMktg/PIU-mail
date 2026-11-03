<?php

use App\Jobs\ImportParcelPage;
use App\Models\County;
use Database\Seeders\CountySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

test('piu:check-postgis reports the PostGIS version', function () {
    $this->artisan('piu:check-postgis')
        ->expectsOutputToContain('POSTGIS=')
        ->assertSuccessful();
});

test('all 21 NJ counties are seeded', function () {
    $this->seed(CountySeeder::class);

    expect(County::count())->toBe(21)
        ->and(County::where('name', 'MORRIS')->value('fips'))->toBe('34027')
        ->and(County::where('name', 'CAPE MAY')->exists())->toBeTrue();
});

test('piu:check-postgis fails loudly when PostGIS is not enabled', function () {
    // Inside the RefreshDatabase transaction, so this is rolled back.
    DB::statement('DROP EXTENSION postgis CASCADE');

    $this->artisan('piu:check-postgis')
        ->expectsOutputToContain('not enabled')
        ->assertFailed();
});

test('statewide import queues one chain per county', function () {
    Queue::fake();
    $this->seed(CountySeeder::class);

    $this->artisan('piu:import-nj-parcels')->assertSuccessful();

    Queue::assertPushed(ImportParcelPage::class, 21);
    expect(County::where('status', 'queued')->count())->toBe(21);
});
