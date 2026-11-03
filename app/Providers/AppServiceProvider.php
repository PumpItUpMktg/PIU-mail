<?php

namespace App\Providers;

use App\Parcels\Source\ArcGisNjParcelSource;
use App\Parcels\Source\NjParcelSource;
use App\Support\CurrentTenant;
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
        //
    }
}
