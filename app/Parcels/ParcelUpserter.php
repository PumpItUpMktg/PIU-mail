<?php

namespace App\Parcels;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Idempotent page upsert into parcels.
 *
 * Key: pcl_guid when present, else (pams_pin, county, site_address). Rows are
 * updated in place, so parcel IDs are stable across re-imports (Target Areas
 * reference them). A key seen twice in one run is logged as a duplicate and
 * the first occurrence wins; a retried page (same run + same offset) updates.
 */
final class ParcelUpserter
{
    /** Keep well under Postgres' 65,535 bind-parameter limit. */
    private const CHUNK = 400;

    /** @var list<string> */
    public const COLUMNS = [
        'pcl_guid', 'pams_pin', 'pin_nodup', 'gis_pin', 'cd_code', 'block', 'lot', 'qcode',
        'county', 'municipality', 'site_address',
        'prop_class', 'prop_use', 'dwell_units', 'comm_units',
        'yr_constr', 'bldg_desc_raw', 'stories', 'exterior_code', 'exterior', 'style_code', 'style',
        'garage_cars', 'garage_type', 'bldg_class',
        'land_desc_raw', 'calc_acre',
        'land_val', 'imprvt_val', 'net_value', 'last_yr_tax',
        'sale_price', 'sale_nominal', 'sales_code', 'deed_book', 'deed_page', 'deed_date',
        'pcl_last_upd', 'pcl_pub_date', 'owner_occupied',
    ];

    /**
     * @param  list<MappedParcel>  $parcels
     * @return array{inserted: int, updated: int, duplicates: int}
     */
    public function upsert(array $parcels, string $reportId, int $offset, ?string $layerVersion): array
    {
        // In-page duplicates: Postgres can't touch one row twice per statement.
        $unique = [];
        $duplicates = 0;
        foreach ($parcels as $p) {
            $key = $p->upsertKey();
            if (isset($unique[$key])) {
                $duplicates++;

                continue;
            }
            $unique[$key] = $p;
        }

        $withGuid = array_values(array_filter($unique, fn (MappedParcel $p) => $p->columns['pcl_guid'] !== null));
        $withoutGuid = array_values(array_filter($unique, fn (MappedParcel $p) => $p->columns['pcl_guid'] === null));

        $stats = ['inserted' => 0, 'updated' => 0, 'duplicates' => $duplicates];

        DB::transaction(function () use ($withGuid, $withoutGuid, $reportId, $offset, $layerVersion, &$stats) {
            $conflicts = [
                '(pcl_guid) WHERE pcl_guid IS NOT NULL' => $withGuid,
                '(pams_pin, county, site_address) WHERE pcl_guid IS NULL' => $withoutGuid,
            ];

            foreach ($conflicts as $target => $rows) {
                foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                    $r = $this->upsertChunk($chunk, $target, $reportId, $offset, $layerVersion);
                    $stats['inserted'] += $r['inserted'];
                    $stats['updated'] += $r['updated'];
                    $stats['duplicates'] += count($chunk) - $r['inserted'] - $r['updated'];
                }
            }
        });

        return $stats;
    }

    /**
     * @param  list<MappedParcel>  $chunk
     * @return array{inserted: int, updated: int}
     */
    private function upsertChunk(array $chunk, string $conflictTarget, string $reportId, int $offset, ?string $layerVersion): array
    {
        $cols = [...self::COLUMNS, 'source_attributes', 'geom', 'id', 'source_layer_version',
            'last_import_report_id', 'last_import_offset', 'imported_at', 'created_at', 'updated_at'];

        $rowSql = [];
        $bindings = [];
        $now = now()->toDateTimeString();

        foreach ($chunk as $p) {
            $placeholders = [];
            foreach (self::COLUMNS as $c) {
                $placeholders[] = '?';
                $value = $p->columns[$c] ?? null;
                $bindings[] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
            $placeholders[] = '?::jsonb';
            $bindings[] = json_encode($p->sourceAttributes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $placeholders[] = 'ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))';
            $bindings[] = $p->geometryJson;
            array_push($placeholders, '?', '?', '?', '?', '?', '?', '?');
            array_push($bindings, strtolower((string) Str::ulid()), $layerVersion, $reportId, $offset, $now, $now, $now);

            $rowSql[] = '('.implode(', ', $placeholders).')';
        }

        $updates = array_map(
            fn (string $c) => "{$c} = EXCLUDED.{$c}",
            [...self::COLUMNS, 'source_attributes', 'geom', 'source_layer_version',
                'last_import_report_id', 'last_import_offset', 'imported_at', 'updated_at'],
        );

        // Skip (= duplicate) when this run already wrote the row from a
        // different page. Same page => a retry => update.
        $sql = 'INSERT INTO parcels ('.implode(', ', $cols).') VALUES '.implode(', ', $rowSql)
            ." ON CONFLICT {$conflictTarget} DO UPDATE SET ".implode(', ', $updates)
            .' WHERE parcels.last_import_report_id IS DISTINCT FROM EXCLUDED.last_import_report_id'
            .' OR parcels.last_import_offset IS NOT DISTINCT FROM EXCLUDED.last_import_offset'
            .' RETURNING id, (xmax = 0) AS inserted';

        $returned = DB::select($sql, $bindings);

        // centroid and site_zip are set by the parcels_set_derived trigger as
        // the row is written: no follow-up UPDATE, so no dead row copies.

        $inserted = count(array_filter($returned, fn ($r) => (bool) $r->inserted));

        return ['inserted' => $inserted, 'updated' => count($returned) - $inserted];
    }
}
