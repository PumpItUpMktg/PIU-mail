<?php

use App\Parcels\Source\ArcGisNjParcelSource;
use App\Parcels\Source\SourceRequestException;
use Illuminate\Support\Facades\Http;

test('pages the FeatureServer with the expected query parameters', function () {
    $page = file_get_contents(fixturePath('nj_parcels_morris_page.geojson'));
    Http::fake([
        'example.test/FeatureServer/0/query*' => Http::response($page),
        'example.test/FeatureServer/0*' => Http::response(['name' => 'Parcels', 'objectIdField' => 'OBJECTID', 'maxRecordCount' => 2000]),
    ]);

    $result = (new ArcGisNjParcelSource('https://example.test/FeatureServer/0'))->page('Morris', 2000, 2000);

    expect($result->features)->toHaveCount(6);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/query')
        && $r['where'] === "UPPER(COUNTY) = 'MORRIS'"
        && $r['outSR'] == 4326 && $r['f'] === 'geojson'
        && $r['resultOffset'] == 2000 && $r['resultRecordCount'] == 2000
        && $r['orderByFields'] === 'OBJECTID ASC');
});

test('ArcGIS error envelopes become exceptions', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 400, 'message' => 'Invalid query']])]);

    expect(fn () => (new ArcGisNjParcelSource('https://example.test/FeatureServer/0'))->count('MORRIS'))
        ->toThrow(SourceRequestException::class, 'ArcGIS error 400');
});

test('a response over the size cap is abandoned with a clear error', function () {
    Http::fake([
        'example.test/FeatureServer/0/query*' => Http::response(str_repeat('x', 5000)),
        'example.test/FeatureServer/0*' => Http::response(['objectIdField' => 'OBJECTID']),
    ]);

    $source = new ArcGisNjParcelSource('https://example.test/FeatureServer/0', maxResponseBytes: 1000);

    expect(fn () => $source->page('MORRIS', 0, 2000))
        ->toThrow(SourceRequestException::class, 'response too large');
});

test('a response under the cap decodes normally', function () {
    Http::fake([
        'example.test/FeatureServer/0/query*' => Http::response(file_get_contents(fixturePath('nj_parcels_morris_page.geojson'))),
        'example.test/FeatureServer/0*' => Http::response(['objectIdField' => 'OBJECTID']),
    ]);

    $page = (new ArcGisNjParcelSource('https://example.test/FeatureServer/0', maxResponseBytes: 10 * 1024 * 1024))->page('MORRIS', 0, 2000);

    expect($page->features)->toHaveCount(6);
});
