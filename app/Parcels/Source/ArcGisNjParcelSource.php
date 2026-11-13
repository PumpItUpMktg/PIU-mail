<?php

namespace App\Parcels\Source;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pages the NJOGIS parcel composite over ArcGIS REST (no GDAL needed).
 */
class ArcGisNjParcelSource implements NjParcelSource
{
    public function __construct(
        private readonly string $layerUrl,
        private readonly int $timeout = 120,
        private readonly string $countyField = 'COUNTY',
        private readonly int $maxResponseBytes = 16 * 1024 * 1024,
    ) {}

    public function layerInfo(): array
    {
        return Cache::remember('piu:nj-parcels:layer-info:'.md5($this->layerUrl), 3600, function () {
            return $this->get($this->layerUrl, ['f' => 'json'], 'layer info');
        });
    }

    public function layerVersion(): ?string
    {
        $info = $this->layerInfo();
        $ms = $info['editingInfo']['dataLastEditDate'] ?? $info['editingInfo']['lastEditDate'] ?? null;

        return $ms ? gmdate('Y-m-d\TH:i:s\Z', intdiv((int) $ms, 1000)) : null;
    }

    public function count(string $county, ?string $extraWhere = null): int
    {
        $where = $this->countyWhere($county).($extraWhere ? " AND ({$extraWhere})" : '');
        $json = $this->get($this->layerUrl.'/query', ['where' => $where, 'returnCountOnly' => 'true', 'f' => 'json'], 'count');

        return (int) ($json['count'] ?? 0);
    }

    public function page(string $county, int $offset, int $limit): SourcePage
    {
        $json = $this->get($this->layerUrl.'/query', [
            'where' => $this->countyWhere($county),
            'outFields' => '*',
            'outSR' => 4326,
            'returnGeometry' => 'true',
            // 6 decimal places of a degree is ~10 cm, finer than the source's
            // own accuracy; roughly halves the payload (Ocean pages hit 132 MB).
            'geometryPrecision' => 6,
            'orderByFields' => $this->objectIdField().' ASC',
            'resultOffset' => $offset,
            'resultRecordCount' => $limit,
            'f' => 'geojson',
        ], 'page');

        $features = array_values($json['features'] ?? []);
        $exceeded = (bool) ($json['exceededTransferLimit'] ?? $json['properties']['exceededTransferLimit'] ?? false);

        return new SourcePage($features, $exceeded);
    }

    public function dateRangesByCounty(): array
    {
        $stats = [];
        foreach (['PCL_PBDATE' => ['pub_min', 'pub_max'], 'PCLLASTUPD' => ['upd_min', 'upd_max']] as $field => [$min, $max]) {
            $stats[] = ['statisticType' => 'min', 'onStatisticField' => $field, 'outStatisticFieldName' => $min];
            $stats[] = ['statisticType' => 'max', 'onStatisticField' => $field, 'outStatisticFieldName' => $max];
        }
        $stats[] = ['statisticType' => 'count', 'onStatisticField' => $this->objectIdField(), 'outStatisticFieldName' => 'n'];

        $json = $this->get($this->layerUrl.'/query', [
            'where' => '1=1',
            'groupByFieldsForStatistics' => $this->countyField,
            'outStatistics' => json_encode($stats),
            'f' => 'json',
        ], 'date ranges');

        $out = [];
        foreach ($json['features'] ?? [] as $f) {
            $a = array_change_key_case($f['attributes'] ?? [], CASE_LOWER);
            $county = strtoupper(trim((string) ($a[strtolower($this->countyField)] ?? '')));
            $out[$county] = [
                'pub_min' => $a['pub_min'] ?? null,
                'pub_max' => $a['pub_max'] ?? null,
                'upd_min' => $a['upd_min'] ?? null,
                'upd_max' => $a['upd_max'] ?? null,
                'count' => (int) ($a['n'] ?? 0),
            ];
        }
        ksort($out);

        return $out;
    }

    private function objectIdField(): string
    {
        return (string) ($this->layerInfo()['objectIdField'] ?? 'OBJECTID');
    }

    private function countyWhere(string $county): string
    {
        $value = str_replace("'", "''", strtoupper($county));

        return "UPPER({$this->countyField}) = '{$value}'";
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $url, array $query, string $operation): array
    {
        try {
            $response = $this->http()->get($url, $query);
        } catch (ConnectionException $e) {
            throw new SourceRequestException("NJ parcel source {$operation}: connection failed ({$e->getMessage()})");
        }

        return $this->decode($response, $operation);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $operation): array
    {
        if (! $response->successful()) {
            // Deliberately no body: it could contain owner fields.
            throw new SourceRequestException("NJ parcel source {$operation}: HTTP {$response->status()}");
        }

        $json = json_decode($this->readCapped($response, $operation), true);
        if (! is_array($json)) {
            throw new SourceRequestException("NJ parcel source {$operation}: response was not JSON");
        }

        if (isset($json['error'])) {
            $code = $json['error']['code'] ?? '?';
            $message = mb_substr((string) ($json['error']['message'] ?? 'unknown'), 0, 200);
            throw new SourceRequestException("NJ parcel source {$operation}: ArcGIS error {$code}: {$message}");
        }

        return $json;
    }

    /**
     * Read the body as a stream and stop as soon as it passes the size cap,
     * before it can exhaust the worker's memory. A huge page (Ocean's coastal
     * parcels reached 132 MB per 2,000 records) then fails fast and the
     * importer fetches it in smaller pieces instead.
     */
    private function readCapped(Response $response, string $operation): string
    {
        $stream = $response->toPsrResponse()->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(1024 * 1024);
            if (strlen($body) > $this->maxResponseBytes) {
                $stream->close();
                $mb = round($this->maxResponseBytes / 1024 / 1024, 1);

                throw new SourceRequestException("NJ parcel source {$operation}: response too large (over {$mb} MB)");
            }
        }

        return $body;
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->connectTimeout(20)
            ->withOptions(['stream' => true, 'read_timeout' => 60])
            ->acceptJson()
            ->withUserAgent('PIU-mail parcel importer')
            // Retry brief connection blips, but not timeouts: a page that timed
            // out will time out again, and the importer splits it instead.
            ->retry(3, 2000, fn ($e) => ! str_contains($e->getMessage(), 'timed out'), throw: false);
    }
}
