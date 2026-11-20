<?php

namespace App\Parcels;

use App\Enums\CountyStatus;
use App\Enums\ImportStatus;
use App\Jobs\ImportParcelPage;
use App\Models\County;
use App\Models\ImportReport;
use App\Models\ImportReportPage;
use App\Parcels\Source\NjParcelSource;
use App\Parcels\Source\SourcePage;
use App\Parcels\Source\SourceRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates a county import: one report per run, one queued job per page
 * (each page job queues the next), then a finalize step (ZCTA join + totals).
 * Each county is its own chain, so one county failing never stops another.
 */
class ParcelImporter
{
    public function __construct(
        private readonly NjParcelSource $source,
        private readonly ParcelMapper $mapper,
        private readonly ParcelUpserter $upserter,
        private readonly ZctaJoiner $zcta,
    ) {}

    public function start(County $county, string $trigger = 'manual'): ImportReport
    {
        $report = ImportReport::create([
            'county' => $county->name,
            'trigger' => $trigger,
            'status' => ImportStatus::Running,
            'started_at' => now(),
            'counts_by_class' => [],
            'skipped_classes' => [],
            'notes' => [],
        ]);

        $county->update(['status' => CountyStatus::Queued, 'last_error' => null]);

        ImportParcelPage::dispatch($report->id, $county->name, 0);

        return $report;
    }

    /**
     * Fetch, map, and upsert one page of $limit records (default: the page
     * size) at $offset, with exactly ONE source request (plus at most two
     * degraded retries for a single parcel), so no job can outrun its timeout.
     *
     * If the source fails on the page (a timeout, a response over the size
     * cap, or an ArcGIS error from a few enormous or malformed outlines), this
     * writes nothing and returns a split: the next job retries the same offset
     * with half the records. After a successful smaller page the size doubles
     * back toward the page size. A single parcel that still fails is fetched
     * with a server-simplified outline, then without one (tagged in
     * geometry_note); if even that fails the error is raised and the county
     * fails. Nothing is ever skipped.
     */
    public function importPage(ImportReport $report, string $county, int $offset, ?int $limit = null): PageResult
    {
        County::where('name', $county)->update(['status' => CountyStatus::Loading]);

        $pageSize = (int) config('piu.parcels.page_size', 2000);
        $limit = max(1, min($limit ?? $pageSize, $pageSize));

        if ($offset === 0 && $report->expected_count === null) {
            $report->update([
                'source_layer_version' => $this->source->layerVersion(),
                'expected_count' => $this->source->count($county),
            ]);
        }

        try {
            $page = $this->source->page($county, $offset, $limit);
        } catch (SourceRequestException $e) {
            if ($limit > 1) {
                $half = intdiv($limit, 2);
                Log::warning('piu: parcel page failed, splitting', [
                    'county' => $county, 'offset' => $offset, 'limit' => $limit, 'next_limit' => $half, 'error' => $e->getMessage(),
                ]);

                return new PageResult(true, $offset, $half, true);
            }

            $page = $this->fetchDegraded($county, $offset, $e);
        }

        $classes = (array) config('piu.parcels.import_classes', []);

        $stats = new PageStats;
        $stats->data['fetched'] = count($page->features);
        $keep = [];

        foreach ($page->features as $feature) {
            $mapped = $this->mapper->map($feature);
            if ($classes !== [] && ! in_array($mapped->columns['prop_class'], $classes, true)) {
                $stats->skip($mapped);

                continue;
            }
            $stats->observe($mapped);
            $keep[] = $mapped;
        }

        $written = $this->upserter->upsert($keep, $report->id, $offset, $report->source_layer_version);
        $stats->data = array_merge($stats->data, $written);

        ImportReportPage::updateOrCreate(
            ['import_report_id' => $report->id, 'offset' => $offset],
            ['stats' => $stats->data],
        );

        Log::info('piu: parcel page imported', [
            'county' => $county, 'offset' => $offset, 'limit' => $limit,
            'fetched' => $stats->data['fetched'], 'inserted' => $written['inserted'],
            'updated' => $written['updated'], 'duplicates' => $written['duplicates'],
        ]);

        $fetched = count($page->features);

        return new PageResult(
            split: false,
            nextOffset: $offset + $fetched,
            nextLimit: $limit < $pageSize ? min($pageSize, $limit * 2) : $pageSize,
            more: $fetched > 0 && ($page->exceededTransferLimit || $fetched >= $limit),
        );
    }

    /** Key added to a feature fetched without its full outline. */
    public const GEOMETRY_NOTE = 'piu_geometry_note';

    private function fetchDegraded(string $county, int $offset, SourceRequestException $original): SourcePage
    {
        foreach (['simplified' => 'simplified', 'none' => 'unavailable'] as $mode => $note) {
            try {
                $page = $this->source->page($county, $offset, 1, $mode);
            } catch (SourceRequestException) {
                continue;
            }

            Log::warning('piu: parcel imported with degraded outline', [
                'county' => $county, 'offset' => $offset, 'outline' => $note, 'error' => $original->getMessage(),
            ]);

            $features = array_map(function (array $f) use ($note) {
                $f[self::GEOMETRY_NOTE] = $note;

                return $f;
            }, $page->features);

            return new SourcePage($features, $page->exceededTransferLimit);
        }

        throw $original;
    }

    public function finalize(ImportReport $report): void
    {
        $county = $report->county;
        $totals = PageStats::sum($report->pages()->pluck('stats'));

        $notes = $report->notes ?? [];
        $noZip = null;
        if ($this->zcta->zctasLoaded()) {
            $noZip = $this->zcta->assignCounty($county);
        } else {
            $notes[] = 'ZCTAs not loaded: site_zip not assigned. Run piu:load-zctas, then re-import.';
        }

        if (($totals['geometry_simplified'] ?? 0) > 0) {
            $notes[] = "{$totals['geometry_simplified']} parcels imported with a server-simplified outline (~5 m): the source could not serve their full outline.";
        }
        if (($totals['geometry_unavailable'] ?? 0) > 0) {
            $notes[] = "{$totals['geometry_unavailable']} parcels imported without an outline: the source could not serve it at all.";
        }

        $rowCount = DB::table('parcels')->where('county', $county)->count();
        $stale = DB::table('parcels')->where('county', $county)
            ->where(fn ($q) => $q->whereNull('last_import_report_id')->orWhere('last_import_report_id', '!=', $report->id))
            ->count();
        if ($stale > 0) {
            $notes[] = "{$stale} existing parcel rows were not in this run's source data (kept, not deleted).";
        }

        $report->update([
            'status' => ImportStatus::Completed,
            'fetched' => $totals['fetched'],
            'inserted' => $totals['inserted'],
            'updated' => $totals['updated'],
            'skipped_by_class' => $totals['skipped_by_class'],
            'duplicates' => $totals['duplicates'],
            'null_year' => $totals['null_year'],
            'null_geometry' => $totals['null_geometry'],
            'no_zip' => $noZip ?? 0,
            'bldg_desc_present' => $totals['bldg_desc_present'],
            'bldg_desc_decoded' => $totals['bldg_desc_decoded'],
            'counts_by_class' => $totals['counts_by_class'],
            'skipped_classes' => $totals['skipped_classes'],
            'source_pub_date_min' => $totals['pub_min'],
            'source_pub_date_max' => $totals['pub_max'],
            'source_last_upd_min' => $totals['upd_min'],
            'source_last_upd_max' => $totals['upd_max'],
            'notes' => $notes,
            'finished_at' => now(),
        ]);

        County::where('name', $county)->update([
            'status' => CountyStatus::Loaded,
            'loaded_at' => now(),
            'source_pub_date' => $totals['pub_max'],
            'row_count' => $rowCount,
            'last_error' => null,
        ]);
    }

    public function fail(string $reportId, string $county, string $error): void
    {
        $error = mb_substr($error, 0, 1000);

        ImportReport::whereKey($reportId)->update([
            'status' => ImportStatus::Failed,
            'error' => $error,
            'finished_at' => now(),
        ]);

        County::where('name', $county)->update([
            'status' => CountyStatus::Failed,
            'last_error' => $error,
        ]);
    }
}
