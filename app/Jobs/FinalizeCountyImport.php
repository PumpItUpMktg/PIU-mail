<?php

namespace App\Jobs;

use App\Models\ImportReport;
use App\Parcels\ParcelImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class FinalizeCountyImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(public string $reportId) {}

    public function handle(ParcelImporter $importer): void
    {
        $importer->finalize(ImportReport::findOrFail($this->reportId));
    }

    public function failed(?Throwable $e): void
    {
        $report = ImportReport::find($this->reportId);
        if ($report) {
            app(ParcelImporter::class)->fail($report->id, $report->county, 'Finalize failed: '.($e?->getMessage() ?? 'unknown error'));
        }
    }
}
