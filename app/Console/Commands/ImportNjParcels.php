<?php

namespace App\Console\Commands;

use App\Models\County;
use App\Parcels\ParcelImporter;
use App\Parcels\ParcelMapper;
use App\Parcels\PageStats;
use App\Parcels\Source\NjParcelSource;
use Illuminate\Console\Command;
use Throwable;

class ImportNjParcels extends Command
{
    protected $signature = 'piu:import-nj-parcels
    {--county=* : One or more county names (e.g. MORRIS); omit for statewide}
    {--dry-run : Count and sample the first page only; write nothing}';

    protected $description = 'Queue an import of NJ parcels, one job chain per county';

    public function handle(ParcelImporter $importer, NjParcelSource $source, ParcelMapper $mapper): int
    {
        $requested = array_map(fn ($c) => County::normalizeName((string) $c), (array) $this->option('county'));
        $counties = County::query()
            ->when($requested !== [], fn ($q) => $q->whereIn('name', $requested))
            ->orderBy('name')
            ->get();

        $unknown = array_diff($requested, $counties->pluck('name')->all());
        if ($unknown !== []) {
            $this->error('Unknown county: '.implode(', ', $unknown).'. Did you run the CountySeeder?');

            return self::FAILURE;
        }
        if ($counties->isEmpty()) {
            $this->error('No counties registered. Run `php artisan db:seed --class=CountySeeder`.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($counties->pluck('name')->all(), $source, $mapper);
        }

        foreach ($counties as $county) {
            $report = $importer->start($county);
            $this->info("Queued {$county->name} (report {$report->id})");
        }

        $this->line('Each county runs as its own queued job chain. Watch progress in /admin (Counties, Import Reports).');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $names
     */
    private function dryRun(array $names, NjParcelSource $source, ParcelMapper $mapper): int
    {
        $rows = [];
        $failed = false;

        foreach ($names as $name) {
            try {
                $count = $source->count($name);
                $page = $source->page($name, 0, min(500, (int) config('piu.parcels.page_size')));
                $stats = new PageStats;
                foreach ($page->features as $f) {
                    $stats->observe($mapper->map($f));
                }
                $d = $stats->data;
                $rate = $d['bldg_desc_present'] ? round($d['bldg_desc_decoded'] / $d['bldg_desc_present'] * 100, 1).'%' : 'n/a';
                $rows[] = [$name, number_format($count), count($page->features), $d['counts_by_class']['2'] ?? 0, $rate];
            } catch (Throwable $e) {
                $failed = true;
                $rows[] = [$name, 'ERROR', '-', '-', $e->getMessage()];
            }
        }

        $this->table(['County', 'Source rows', 'Sampled', 'Class 2 in sample', 'BLDG_DESC decode (sample)'], $rows);
        $this->line('Dry run: nothing was written.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
