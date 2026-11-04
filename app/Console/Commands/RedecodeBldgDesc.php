<?php

namespace App\Console\Commands;

use App\Parcels\BldgDescDecoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-applies the current BldgDescDecoder to parcels already in the database,
 * from bldg_desc_raw, without re-importing. Works one distinct code at a
 * time, and only touches rows whose decoded columns actually change.
 */
class RedecodeBldgDesc extends Command
{
    protected $signature = 'piu:redecode-bldg-desc {--county= : Limit to one county}';

    protected $description = 'Re-decode BLDG_DESC for imported parcels with the current decoder (no re-import)';

    public function handle(BldgDescDecoder $decoder): int
    {
        $county = $this->option('county') ? strtoupper((string) $this->option('county')) : null;

        $codes = DB::table('parcels')
            ->when($county, fn ($q) => $q->where('county', $county))
            ->whereNotNull('bldg_desc_raw')
            ->distinct()
            ->pluck('bldg_desc_raw');

        $this->info("{$codes->count()} distinct BLDG_DESC values to re-decode".($county ? " in {$county}" : ''));
        $changed = 0;

        foreach ($codes as $raw) {
            $d = $decoder->decode($raw);
            $values = [
                'stories' => $d?->stories,
                'exterior_code' => $d?->exteriorCode,
                'exterior' => $d?->exterior,
                'style_code' => $d?->styleCode,
                'style' => $d?->style,
                'garage_cars' => $d?->garageCars,
                'garage_type' => $d?->garageType?->value,
            ];

            $changed += DB::table('parcels')
                ->when($county, fn ($q) => $q->where('county', $county))
                ->where('bldg_desc_raw', $raw)
                ->where(function ($q) use ($values) {
                    foreach ($values as $column => $value) {
                        $q->orWhereRaw("{$column} IS DISTINCT FROM ?", [$value]);
                    }
                })
                ->update($values + ['updated_at' => now()]);
        }

        $this->info("Updated {$changed} parcels.");

        return self::SUCCESS;
    }
}
