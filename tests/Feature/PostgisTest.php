<?php

use App\Jobs\ImportParcelPage;
use App\Models\County;
use App\Support\DatabaseNotConfiguredException;
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

test('piu:check-postgis explains when no database is attached', function () {
    config(['database.connections.unattached' => ['driver' => 'pgsql', 'url' => null, 'host' => null, 'database' => 'x']]);
    config(['database.default' => 'unattached']);

    $this->artisan('piu:check-postgis')
        ->expectsOutputToContain('No database is attached: DB_HOST and DB_URL are both empty. In Laravel Cloud: attach a Postgres database')
        ->assertFailed();

    config(['database.default' => 'pgsql']);
});

test('piu:check-postgis rejects a non-Postgres connection such as SQLite', function () {
    config(['database.connections.lite' => ['driver' => 'sqlite', 'database' => ':memory:']]);
    config(['database.default' => 'lite']);

    $this->artisan('piu:check-postgis')
        ->expectsOutputToContain('requires Postgres with PostGIS')
        ->assertFailed();

    config(['database.default' => 'pgsql']);
});

test('opening an unconfigured connection throws the clear message, never a SQLite fallback', function (array $config, string $message) {
    config(['database.connections.probe' => $config]);

    expect(fn () => DB::connection('probe')->getPdo())
        ->toThrow(DatabaseNotConfiguredException::class, $message);
})->with([
    'no host or url' => [['driver' => 'pgsql', 'url' => null, 'host' => null, 'database' => 'x'], 'No database is attached'],
    'sqlite' => [['driver' => 'sqlite', 'database' => ':memory:'], 'requires Postgres with PostGIS'],
]);

test('the default connection is Postgres when DB_CONNECTION is unset', function () {
    $config = require base_path('config/database.php');

    expect($config['default'])->toBe(env('DB_CONNECTION') ?? 'pgsql')
        ->and(file_get_contents(base_path('config/database.php')))->toContain("env('DB_CONNECTION', 'pgsql')");
});
