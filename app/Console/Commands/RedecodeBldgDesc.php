<?php

namespace App\Console\Commands;

use App\Parcels\BldgDescDecoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-applies the current BldgDescDecoder to parcels already in the database,
 * from bldg_desc_raw, without re-importing.
 *
 * Each distinct code is decoded once in PHP into a temporary lookup table,
 * then ONE set-based UPDATE per county joins parcels to it. (Updating per
 * code meant one full scan of the county per distinct code: 12,475 scans
 * for Morris.) Only rows whose decoded values change are written.
 */
class RedecodeBldgDesc extends Command
{
    protected $signature = 'piu:redecode-bldg-desc {--county= : Limit to one county}';

    protected $description = 'Re-decode BLDG_DESC for imported parcels with the current decoder (no re-import)';

    private const COLUMNS = ['stories', 'exterior_code', 'exterior', 'style_code', 'style', 'garage_cars', 'garage_type'];

    public function handle(BldgDescDecoder $decoder): int
    {
        $county = $this->option('county') ? strtoupper((string) $this->option('county')) : null;

        $codes = DB::table('parcels')
            ->when($county, fn ($q) => $q->where('county', $county))
            ->whereNotNull('bldg_desc_raw')
            ->distinct()
            ->pluck('bldg_desc_raw');

        $this->info("{$codes->count()} distinct BLDG_DESC values to re-decode".($county ? " in {$county}" : ''));

        DB::statement('DROP TABLE IF EXISTS bldg_desc_decodes');
        DB::statement(<<<'SQL'
            CREATE TEMPORARY TABLE bldg_desc_decodes (
                raw text PRIMARY KEY,
                stories numeric(4,2),
                exterior_code varchar(4),
                exterior varchar(255),
                style_code varchar(2),
                style varchar(255),
                garage_cars smallint,
                garage_type varchar(16)
            )
            SQL);

        foreach ($codes->chunk(1000) as $chunk) {
            DB::table('bldg_desc_decodes')->insert($chunk->map(function ($raw) use ($decoder) {
                $d = $decoder->decode($raw);

                return [
                    'raw' => $raw,
                    'stories' => $d?->stories,
                    'exterior_code' => $d?->exteriorCode,
                    'exterior' => $d?->exterior,
                    'style_code' => $d?->styleCode,
                    'style' => $d?->style,
                    'garage_cars' => $d?->garageCars,
                    'garage_type' => $d?->garageType?->value,
                ];
            })->values()->all());
        }

        $counties = $county !== null
            ? [$county]
            : DB::table('parcels')->distinct()->orderBy('county')->pluck('county')->all();

        $set = implode(', ', array_map(fn ($c) => "{$c} = d.{$c}", self::COLUMNS));
        $changed = implode(' OR ', array_map(fn ($c) => "p.{$c} IS DISTINCT FROM d.{$c}", self::COLUMNS));

        $total = 0;
        foreach ($counties as $name) {
            $n = DB::update(
                "UPDATE parcels p SET {$set}, updated_at = now() FROM bldg_desc_decodes d
                 WHERE p.county = ? AND p.bldg_desc_raw = d.raw AND ({$changed})",
                [$name],
            );
            $this->line("{$name}: {$n} parcels updated");
            $total += $n;
        }

        DB::statement('DROP TABLE IF EXISTS bldg_desc_decodes');
        $this->info("Updated {$total} parcels.");

        return self::SUCCESS;
    }
}
