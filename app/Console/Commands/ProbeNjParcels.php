<?php

namespace App\Console\Commands;

use App\Parcels\OwnerFields;
use App\Parcels\SourceValues;
use App\Parcels\Source\NjParcelSource;
use Illuminate\Console\Command;
use Throwable;

/**
 * Phase 0 discovery against the live source. Prints counts and metadata only:
 * owner fields are reported as non-empty COUNTS, never values.
 */
class ProbeNjParcels extends Command
{
    protected $signature = 'piu:probe-nj-parcels {--county=MORRIS : County used for the owner-field check}';

    protected $description = 'Phase 0: report the live NJ parcel layer, field diff, owner-field status and per-county freshness';

    /** Fields the relay spec expects (owner fields included). */
    public const EXPECTED_FIELDS = [
        'PAMS_PIN', 'PCL_MUN', 'PCLBLOCK', 'PCLLOT', 'PCLQCODE', 'PIN_NODUP', 'GIS_PIN', 'CD_CODE', 'PCL_GUID', 'OLD_PROPID',
        'COUNTY', 'MUN_NAME', 'PROP_LOC',
        'PROP_CLASS', 'PROP_USE', 'FAC_NAME',
        'LAND_VAL', 'IMPRVT_VAL', 'NET_VALUE', 'LAST_YR_TX',
        'SALE_PRICE', 'SALES_CODE', 'DEED_BOOK', 'DEED_PAGE', 'DEED_DATE',
        'YR_CONSTR', 'BLDG_DESC', 'BLDG_CLASS',
        'LAND_DESC', 'CALC_ACRE', 'ADD_LOTS1', 'ADD_LOTS2', 'DWELL', 'COMM_DWELL',
        'PCLLASTUPD', 'PCL_PBDATE',
        'OWNER_NAME', 'ST_ADDRESS', 'CITY_STATE', 'ZIP_CODE', 'ZIP5', 'ZIP_PLUS4',
    ];

    public function handle(NjParcelSource $source): int
    {
        try {
            $info = $source->layerInfo();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Layer: '.config('piu.parcels.layer_url'));
        $this->line('Name: '.($info['name'] ?? '?').' | maxRecordCount: '.($info['maxRecordCount'] ?? '?')
            .' | objectIdField: '.($info['objectIdField'] ?? '?').' | version: '.($source->layerVersion() ?? '?'));

        $live = array_map(fn ($f) => strtoupper((string) $f['name']), $info['fields'] ?? []);
        $this->newLine();
        $this->info('Missing from live service: '.(implode(', ', array_diff(self::EXPECTED_FIELDS, $live)) ?: 'none'));
        $this->info('Extra in live service: '.(implode(', ', array_diff($live, self::EXPECTED_FIELDS)) ?: 'none'));
        $this->line('All live fields: '.implode(', ', $live));

        $county = strtoupper((string) $this->option('county'));
        $this->newLine();
        $this->info("Owner fields in {$county} (non-empty counts only, never values):");
        $total = $source->count($county);
        $rows = [];
        foreach (OwnerFields::KEYS as $field) {
            if (! in_array($field, $live, true)) {
                $rows[] = [$field, 'absent from layer', ''];

                continue;
            }
            $n = $source->count($county, "{$field} IS NOT NULL AND {$field} <> ''");
            $rows[] = [$field, number_format($n), $total ? round($n / $total * 100, 1).'%' : '-'];
        }
        $this->table(['Field', "Non-empty of {$total}", '%'], $rows);

        $this->newLine();
        $this->info('Freshness per county:');
        $rows = [];
        foreach ($source->dateRangesByCounty() as $name => $r) {
            $rows[] = [
                $name, number_format($r['count']),
                SourceValues::sourceDate($r['pub_min'])?->toDateString(), SourceValues::sourceDate($r['pub_max'])?->toDateString(),
                SourceValues::sourceDate($r['upd_min'])?->toDateString(), SourceValues::sourceDate($r['upd_max'])?->toDateString(),
            ];
        }
        $this->table(['County', 'Rows', 'PCL_PBDATE min', 'PCL_PBDATE max', 'PCLLASTUPD min', 'PCLLASTUPD max'], $rows);

        return self::SUCCESS;
    }
}
