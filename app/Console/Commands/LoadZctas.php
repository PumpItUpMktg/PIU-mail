<?php

namespace App\Console\Commands;

use App\Parcels\ZctaLoader;
use Illuminate\Console\Command;
use Throwable;

class LoadZctas extends Command
{
    protected $signature = 'piu:load-zctas {--file= : Load from a local GeoJSON file instead of Census TIGERweb}';

    protected $description = 'Load NJ Census ZCTA polygons into the zctas table (once)';

    public function handle(ZctaLoader $loader): int
    {
        try {
            $n = $this->option('file')
                ? $loader->fromFile((string) $this->option('file'))
                : $loader->fromTigerweb();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Loaded {$n} NJ ZCTA polygons.");

        return self::SUCCESS;
    }
}
