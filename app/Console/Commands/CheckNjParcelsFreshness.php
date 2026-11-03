<?php

namespace App\Console\Commands;

use App\Enums\CountyStatus;
use App\Models\County;
use App\Parcels\ParcelImporter;
use App\Parcels\Source\NjParcelSource;
use App\Parcels\SourceValues;
use Illuminate\Console\Command;
use Throwable;

class CheckNjParcelsFreshness extends Command
{
    protected $signature = 'piu:check-nj-parcels-freshness';

    protected $description = 'Re-import any loaded county whose source PCL_PBDATE is newer than the last import';

    public function handle(NjParcelSource $source, ParcelImporter $importer): int
    {
        try {
            $ranges = $source->dateRangesByCounty();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $queued = 0;
        foreach (County::where('status', CountyStatus::Loaded)->orderBy('name')->get() as $county) {
            $sourceMax = SourceValues::sourceDate($ranges[$county->name]['pub_max'] ?? null);
            if ($sourceMax === null) {
                continue;
            }

            $ours = $county->source_pub_date;
            if ($ours === null || $sourceMax->greaterThan($ours)) {
                $report = $importer->start($county, 'freshness');
                $report->addNote(sprintf(
                    'Republished: source PCL_PBDATE %s > last imported %s. Re-import queued.',
                    $sourceMax->toDateString(),
                    $ours?->toDateString() ?? 'none',
                ));
                $this->info("{$county->name}: republished ({$sourceMax->toDateString()}), re-import queued");
                $queued++;
            }
        }

        $this->line("{$queued} county re-import(s) queued.");

        return self::SUCCESS;
    }
}
