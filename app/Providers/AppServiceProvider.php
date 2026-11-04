<?php

namespace App\Providers;

use App\Parcels\Source\ArcGisNjParcelSource;
use App\Parcels\Source\NjParcelSource;
use App\Support\CurrentTenant;
use App\Support\DatabaseGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);

        $this->app->bind(NjParcelSource::class, fn () => new ArcGisNjParcelSource(
            layerUrl: (string) config('piu.parcels.layer_url'),
            timeout: (int) config('piu.parcels.http_timeout', 120),
            countyField: (string) config('piu.parcels.county_field', 'COUNTY'),
        ));
    }

    public function boot(): void
    {
        // Check the config each time a connection is opened (not at boot, so
        // build steps that never touch the database are unaffected).
        foreach (['pgsql', 'sqlite', 'mysql', 'mariadb', 'sqlsrv'] as $driver) {
            $this->app['db']->extend($driver, function (array $config, string $name) {
                DatabaseGuard::assert($name, $config);

                return $this->app['db.factory']->make($config, $name);
            });
        }
    }
}
