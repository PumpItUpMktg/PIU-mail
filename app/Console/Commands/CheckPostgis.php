<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckPostgis extends Command
{
    protected $signature = 'piu:check-postgis';

    protected $description = 'Print postgis_full_version(); exit non-zero if PostGIS is unavailable';

    public function handle(): int
    {
        try {
            $installed = DB::selectOne("SELECT extversion FROM pg_extension WHERE extname = 'postgis'");
            if ($installed === null) {
                $available = DB::selectOne("SELECT default_version FROM pg_available_extensions WHERE name = 'postgis'");
                $this->error($available === null
                    ? 'PostGIS is NOT available on this database server. PIU-mail cannot run without it.'
                    : "PostGIS {$available->default_version} is available but not enabled. Run `php artisan migrate`.");

                return self::FAILURE;
            }

            $version = DB::selectOne('SELECT postgis_full_version() AS v')->v;
        } catch (Throwable $e) {
            $this->error('PostGIS check failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('PostGIS OK');
        $this->line($version);

        return self::SUCCESS;
    }
}
