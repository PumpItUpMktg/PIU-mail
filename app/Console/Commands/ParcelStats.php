<?php

namespace App\Console\Commands;

use App\Parcels\BldgDescDecoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ParcelStats extends Command
{
    protected $signature = 'piu:parcel-stats
    {--county= : Limit to one county}
    {--statewide-rows= : Source row count statewide, to project storage}
    {--sample-bldg=0 : Print N distinct BLDG_DESC codes with their decode, for the test suite}';

    protected $description = 'Report per-class counts, BLDG_DESC decode rate, on-disk size and a statewide projection';

    public function handle(BldgDescDecoder $decoder): int
    {
        $county = $this->option('county') ? strtoupper((string) $this->option('county')) : null;
        $base = fn () => DB::table('parcels')->when($county, fn ($q) => $q->where('county', $county));

        $total = $base()->count();
        $this->info(($county ?? 'All counties').": {$total} parcels");

        $this->table(['PROP_CLASS', 'Rows'], $base()->selectRaw("coalesce(prop_class, '(none)') AS c, count(*) AS n")
            ->groupBy('c')->orderBy('c')->get()->map(fn ($r) => [$r->c, number_format($r->n)])->all());

        foreach (['All classes' => false, 'Residential (class 2)' => true] as $label => $residentialOnly) {
            $q = fn () => $base()->whereNotNull('bldg_desc_raw')->when($residentialOnly, fn ($q) => $q->where('prop_class', '2'));
            $present = $q()->count();
            $pct = fn (int $n) => $present ? round($n / $present * 100, 1).'%' : 'n/a';
            $exterior = $q()->whereNotNull('exterior_code')->count();
            $style = $q()->whereNotNull('style_code')->count();
            $garage = $q()->whereNotNull('garage_type')->count();
            $this->line(sprintf('BLDG_DESC, %s: %d present | stories+exterior %d (%s) | style %d (%s) | garage %d (%s)',
                $label, $present, $exterior, $pct($exterior), $style, $pct($style), $garage, $pct($garage)));
        }

        $this->line('Top BLDG_DESC values with no decode at all:');
        $this->table(['BLDG_DESC', 'Rows'], $base()->whereNotNull('bldg_desc_raw')->whereNull('exterior_code')
            ->selectRaw('bldg_desc_raw AS v, count(*) AS n')->groupBy('v')->orderByDesc('n')->limit(25)->get()
            ->map(fn ($r) => [$r->v, $r->n])->all());

        $this->line(sprintf('Owner-occupied: true %d, false %d, null %d',
            $base()->where('owner_occupied', true)->count(),
            $base()->where('owner_occupied', false)->count(),
            $base()->whereNull('owner_occupied')->count()));
        $this->line('No site_zip: '.$base()->whereNull('site_zip')->count());

        $bytes = (int) DB::selectOne("SELECT pg_total_relation_size('parcels') AS b")->b;
        $allRows = DB::table('parcels')->count();
        $this->line(sprintf('parcels on disk (table + indexes + TOAST): %s (%d rows, %s/row)',
            self::human($bytes), $allRows, $allRows ? self::human(intdiv($bytes, $allRows)) : '-'));

        if ($statewide = (int) $this->option('statewide-rows')) {
            $this->line(sprintf('Projected statewide (%s rows): %s', number_format($statewide),
                $allRows ? self::human((int) ($bytes / $allRows * $statewide)) : '-'));
        }

        if ($n = (int) $this->option('sample-bldg')) {
            $this->table(['BLDG_DESC', 'Rows', 'Decode'], $base()->whereNotNull('bldg_desc_raw')
                ->selectRaw('bldg_desc_raw AS v, count(*) AS n')->groupBy('v')->orderByDesc('n')->limit($n)->get()
                ->map(fn ($r) => [$r->v, $r->n, $decoder->decode($r->v)?->display() ?? '(undecoded)'])->all());
        }

        return self::SUCCESS;
    }

    private static function human(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $unit) {
            if ($bytes < 1024 ** ($i + 1) || $unit === 'TB') {
                return round($bytes / 1024 ** $i, 2).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
