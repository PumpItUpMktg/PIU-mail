<?php

namespace App\Parcels;

use App\Parcels\Source\SourceRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Loads NJ ZCTA polygons (ZIPs 07xxx / 08xxx) into the zctas table, either
 * from the Census TIGERweb ArcGIS layer or from a local GeoJSON file.
 */
final class ZctaLoader
{
    private const ZCTA_FIELDS = ['ZCTA5CE20', 'ZCTA5CE10', 'ZCTA5', 'GEOID20', 'GEOID', 'BASENAME'];

    public function fromFile(string $path, ?string $vintage = null): int
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}");
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || ! isset($json['features'])) {
            throw new RuntimeException("{$path} is not a GeoJSON FeatureCollection");
        }

        return $this->store($json['features'], $vintage ?? basename($path));
    }

    public function fromTigerweb(): int
    {
        $url = rtrim((string) config('piu.zcta.layer_url'), '/').'/query';
        $size = (int) config('piu.zcta.page_size', 200);
        $offset = 0;
        $total = 0;

        do {
            $response = Http::timeout(120)->retry(3, 2000, throw: false)->get($url, [
                'where' => "ZCTA5 LIKE '07%' OR ZCTA5 LIKE '08%'",
                'outFields' => '*',
                'outSR' => 4326,
                'f' => 'geojson',
                'resultOffset' => $offset,
                'resultRecordCount' => $size,
            ]);
            if (! $response->successful() || isset($response->json()['error'])) {
                throw new SourceRequestException('TIGERweb ZCTA query failed: HTTP '.$response->status());
            }
            $features = $response->json('features') ?? [];
            $total += $this->store($features, 'tigerweb');
            $offset += count($features);
        } while (count($features) > 0 && ($response->json('exceededTransferLimit') || $response->json('properties.exceededTransferLimit') || count($features) >= $size));

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $features
     */
    private function store(array $features, string $vintage): int
    {
        $n = 0;
        foreach ($features as $f) {
            $props = array_change_key_case((array) ($f['properties'] ?? []), CASE_UPPER);
            $zcta = null;
            foreach (self::ZCTA_FIELDS as $field) {
                if (! empty($props[$field])) {
                    $zcta = substr(preg_replace('/\D/', '', (string) $props[$field]) ?? '', 0, 5);
                    break;
                }
            }
            $type = $f['geometry']['type'] ?? null;
            if (! $zcta || strlen($zcta) !== 5 || ! in_array($zcta[0].$zcta[1], ['07', '08'], true)
                || ! in_array($type, ['Polygon', 'MultiPolygon'], true)) {
                continue;
            }

            DB::statement(<<<'SQL'
                INSERT INTO zctas (zcta5, geom, source_vintage, created_at, updated_at)
                VALUES (?, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), ?, now(), now())
                ON CONFLICT (zcta5) DO UPDATE SET geom = EXCLUDED.geom, source_vintage = EXCLUDED.source_vintage, updated_at = now()
                SQL, [$zcta, json_encode($f['geometry']), $vintage]);
            $n++;
        }

        return $n;
    }
}
