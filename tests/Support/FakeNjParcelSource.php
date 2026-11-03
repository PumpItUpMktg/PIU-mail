<?php

namespace Tests\Support;

use App\Parcels\Source\NjParcelSource;
use App\Parcels\Source\SourcePage;

/** Serves fixture features instead of the live ArcGIS service. */
class FakeNjParcelSource implements NjParcelSource
{
    /** @var list<array<string, mixed>> */
    public array $features;

    public ?string $pubMax = null;

    public function __construct(?string $fixture = null)
    {
        $json = json_decode((string) file_get_contents($fixture ?? fixturePath('nj_parcels_morris_page.geojson')), true);
        $this->features = $json['features'];
    }

    public function layerInfo(): array
    {
        return ['name' => 'Fake', 'maxRecordCount' => 2000, 'objectIdField' => 'OBJECTID', 'fields' => []];
    }

    public function layerVersion(): ?string
    {
        return '2025-06-30T00:00:00Z';
    }

    public function count(string $county, ?string $extraWhere = null): int
    {
        return count($this->forCounty($county));
    }

    public function page(string $county, int $offset, int $limit): SourcePage
    {
        $all = $this->forCounty($county);
        $slice = array_slice($all, $offset, $limit);

        return new SourcePage($slice, $offset + $limit < count($all));
    }

    public function dateRangesByCounty(): array
    {
        return ['MORRIS' => ['pub_min' => null, 'pub_max' => $this->pubMax, 'upd_min' => null, 'upd_max' => null, 'count' => count($this->features)]];
    }

    /** @return list<array<string, mixed>> */
    private function forCounty(string $county): array
    {
        return array_values(array_filter($this->features, fn ($f) => strtoupper($f['properties']['COUNTY']) === strtoupper($county)));
    }
}
