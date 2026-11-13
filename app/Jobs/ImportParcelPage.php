<?php

namespace App\Jobs;

use App\Models\ImportReport;
use App\Parcels\ParcelImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Throwable;

/**
 * Imports one page of one county. The payload is only (report id, county,
 * offset): source rows, and therefore owner fields, are fetched inside
 * handle() and never serialized onto the queue or into failed_jobs.
 */
class ImportParcelPage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    // Under the worker --timeout (1800) and queue retry_after (1900): a page
    // may be split into several smaller fetches.
    public int $timeout = 1700;

    /** @var list<int> */
    public array $backoff = [10, 30, 90, 300];

    public function __construct(
        public string $reportId,
        public string $county,
        public int $offset,
    ) {}

    public function handle(ParcelImporter $importer): void
    {
        $report = ImportReport::findOrFail($this->reportId);

        $more = $importer->importPage($report, $this->county, $this->offset);

        if ($more) {
            $next = $this->offset + (int) config('piu.parcels.page_size', 2000);
            self::dispatch($this->reportId, $this->county, $next);
        } else {
            FinalizeCountyImport::dispatch($this->reportId);
        }
    }

    public function failed(?Throwable $e): void
    {
        $reason = $e instanceof MaxAttemptsExceededException
            ? 'the worker process was killed on this page on every attempt (most likely out of memory, or over the job timeout)'
            : ($e?->getMessage() ?? 'unknown error');

        app(ParcelImporter::class)->fail(
            $this->reportId,
            $this->county,
            sprintf('Page at offset %d failed: %s', $this->offset, $reason),
        );
    }
}
