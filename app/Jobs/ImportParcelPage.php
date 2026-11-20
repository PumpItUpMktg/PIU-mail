<?php

namespace App\Jobs;

use App\Models\ImportReport;
use App\Parcels\ParcelImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Throwable;

/**
 * Imports one page of one county, then queues the next step of the chain.
 * The payload is only (report id, county, offset, limit): source rows, and
 * therefore owner fields, are fetched inside handle() and never serialized
 * onto the queue or into failed_jobs. Each job makes one source request, so
 * a page the source can't serve is narrowed down across several short jobs
 * (each step saved on the queue) instead of inside one long one.
 */
class ImportParcelPage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    // One source request (plus at most two degraded retries) per job; stays
    // under the worker --timeout (1800) and queue retry_after (1900).
    public int $timeout = 1700;

    /** @var list<int> */
    public array $backoff = [10, 30, 90, 300];

    public function __construct(
        public string $reportId,
        public string $county,
        public int $offset,
        public ?int $limit = null,
    ) {}

    public function handle(ParcelImporter $importer): void
    {
        $report = ImportReport::findOrFail($this->reportId);

        $result = $importer->importPage($report, $this->county, $this->offset, $this->limit);

        if ($result->split || $result->more) {
            self::dispatch($this->reportId, $this->county, $result->nextOffset, $result->nextLimit);
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
            sprintf('Page at offset %d (%d records) failed: %s', $this->offset, $this->limit ?? (int) config('piu.parcels.page_size', 2000), $reason),
        );
    }
}
