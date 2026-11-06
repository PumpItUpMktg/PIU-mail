<?php

use App\Enums\CountyStatus;
use App\Enums\ImportStatus;
use App\Jobs\FinalizeCountyImport;
use App\Jobs\ImportParcelPage;
use App\Models\County;
use App\Models\ImportReport;
use App\Models\Parcel;
use App\Parcels\ParcelImporter;
use App\Parcels\Source\ArcGisNjParcelSource;
use App\Parcels\Source\NjParcelSource;
use App\Parcels\Source\SourcePage;
use App\Parcels\Source\SourceRequestException;
use App\Parcels\ZctaLoader;
use Database\Seeders\CountySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FakeNjParcelSource;

beforeEach(function () {
    $this->seed(CountySeeder::class);
    $this->source = new FakeNjParcelSource;
    $this->app->instance(NjParcelSource::class, $this->source);
    app(ZctaLoader::class)->fromFile(fixturePath('nj_zctas.geojson'));
});

function importMorris(): void
{
    test()->artisan('piu:import-nj-parcels', ['--county' => ['morris']])->assertSuccessful();
}

test('imports a county with typed columns, geometry, zip, owner-occupied and decoded building', function () {
    importMorris();

    // 6 features: one duplicate GUID => 5 parcels.
    expect(Parcel::count())->toBe(5);

    $p = Parcel::where('pcl_guid', '{11111111-1111-1111-1111-111111111111}')->firstOrFail();
    expect($p->county)->toBe('MORRIS')
        ->and($p->municipality)->toBe('MORRIS TOWNSHIP')
        ->and($p->site_address)->toBe('12 NORTH MAIN STREET')
        ->and($p->site_zip)->toBe('07960')
        ->and($p->prop_class)->toBe('2')
        ->and($p->yr_constr)->toBe(1988)
        ->and($p->stories)->toBe(2.0)
        ->and($p->exterior)->toBe('Frame')
        ->and($p->style)->toBe('Colonial')
        ->and($p->garage_cars)->toBe(2)
        ->and($p->garage_type?->value)->toBe('attached')
        ->and($p->calc_acre)->toBe(0.3412)
        ->and($p->net_value)->toBe(550000)
        ->and($p->last_yr_tax)->toBe('11234.56')
        ->and($p->sale_price)->toBe(615000)
        ->and($p->sale_nominal)->toBeFalse()
        ->and($p->deed_date->toDateString())->toBe('2019-07-14')
        ->and($p->pcl_pub_date->toDateString())->toBe('2025-06-30')
        ->and($p->owner_occupied)->toBeTrue()
        ->and($p->bldg_class)->toBe('25')
        ->and($p->source_attributes['PAMS_PIN'])->toBe('1427_10_1')
        ->and($p->summary())->toBe('Built 1988 · 2-story frame Colonial · 2-car attached garage · 0.34 ac · Owner-occupied');

    $geo = DB::selectOne('SELECT GeometryType(geom) AS g, ST_SRID(geom) AS s, GeometryType(centroid) AS c, ST_Within(centroid, geom) AS w FROM parcels WHERE id = ?', [$p->id]);
    expect($geo->g)->toBe('MULTIPOLYGON')->and($geo->s)->toBe(4326)->and($geo->c)->toBe('POINT')->and($geo->w)->toBeTrue();

    $nominal = Parcel::where('pams_pin', '1427_10_2')->first();
    expect($nominal->sale_price)->toBe(1)->and($nominal->sale_nominal)->toBeTrue()
        ->and($nominal->yr_constr)->toBeNull()
        ->and($nominal->owner_occupied)->toBeFalse()
        ->and($nominal->deed_date->toDateString())->toBe('1999-12-31')
        ->and($nominal->style)->toBe('Colonial')->and($nominal->exterior)->toBe('Stone');

    expect(Parcel::where('pams_pin', '1427_10_3')->value('owner_occupied'))->toBeNull(); // owner street blank

    $noGuid = Parcel::where('pams_pin', '1427_11_1')->first();
    expect($noGuid->pcl_guid)->toBeNull()
        ->and($noGuid->site_address)->toBe('3 OAK LANE')
        ->and($noGuid->yr_constr)->toBeNull()            // 2031 is in the future
        ->and($noGuid->bldg_desc_raw)->toBe('NOT A CODE')
        ->and($noGuid->style_code)->toBeNull()          // never guessed
        ->and($noGuid->deed_date)->toBeNull();          // 250229 is not a date

    expect(Parcel::where('prop_class', '4A')->value('site_zip'))->toBeNull(); // outside every NJ ZCTA

    $report = ImportReport::sole();
    expect($report->status)->toBe(ImportStatus::Completed)
        ->and($report->fetched)->toBe(6)
        ->and($report->inserted)->toBe(5)
        ->and($report->updated)->toBe(0)
        ->and($report->duplicates)->toBe(1)
        ->and($report->null_year)->toBe(2)
        ->and($report->no_zip)->toBe(1)
        ->and($report->counts_by_class)->toBe(['2' => 5, '4A' => 1])
        ->and($report->bldg_desc_present)->toBe(6)
        ->and($report->bldg_desc_decoded)->toBe(5);

    $county = County::where('name', 'MORRIS')->first();
    expect($county->status)->toBe(CountyStatus::Loaded)
        ->and($county->row_count)->toBe(5)
        ->and($county->source_pub_date->toDateString())->toBe('2025-06-30');
});

test('re-running the import is idempotent and keeps parcel ids', function () {
    importMorris();
    $ids = Parcel::orderBy('id')->pluck('id')->all();

    importMorris();

    expect(Parcel::count())->toBe(5)
        ->and(Parcel::orderBy('id')->pluck('id')->all())->toBe($ids);

    $second = ImportReport::latest('created_at')->latest('id')->first();
    expect($second->inserted)->toBe(0)->and($second->updated)->toBe(5)->and($second->duplicates)->toBe(1);
});

test('a retried page updates rather than counting duplicates', function () {
    importMorris();
    $report = ImportReport::sole();

    (new ImportParcelPage($report->id, 'MORRIS', 0))->handle(app(ParcelImporter::class));

    expect(Parcel::count())->toBe(5)
        ->and($report->pages()->where('offset', 0)->first()->stats['updated'])->toBe(5);
});

test('duplicates across pages are logged, not failed', function () {
    config(['piu.parcels.page_size' => 2]);
    importMorris();

    $report = ImportReport::sole();
    expect($report->status)->toBe(ImportStatus::Completed)
        ->and($report->duplicates)->toBe(1)
        ->and($report->pages()->count())->toBe(4) // 3 full pages + the empty one that ends the chain
        ->and(Parcel::count())->toBe(5);
});

test('class whitelist skips other classes and reports them', function () {
    config(['piu.parcels.import_classes' => ['2']]);
    importMorris();

    $report = ImportReport::sole();
    expect(Parcel::count())->toBe(4)
        ->and($report->skipped_by_class)->toBe(1)
        ->and($report->skipped_classes)->toBe(['4A' => 1]);
});

test('residential scope is class 2 single-family', function () {
    importMorris();

    expect(Parcel::residential()->count())->toBe(4)
        ->and(Parcel::residential()->where('prop_class', '4A')->exists())->toBeFalse();
});

test('owner fields are never persisted anywhere', function () {
    importMorris();

    $ownerKeys = ['OWNER_NAME', 'ST_ADDRESS', 'CITY_STATE', 'ZIP_CODE', 'ZIP5', 'ZIP_PLUS4'];

    foreach (Parcel::all() as $p) {
        expect(array_intersect(array_map('strtoupper', array_keys($p->source_attributes)), $ownerKeys))->toBe([]);
    }

    $columns = array_map('strtoupper', Schema::getColumnListing('parcels'));
    expect(array_intersect($columns, $ownerKeys))->toBe([]);

    // Scan every table's full text for the planted sentinel values.
    $dump = '';
    foreach (['parcels', 'import_reports', 'import_report_pages', 'jobs', 'failed_jobs', 'counties'] as $table) {
        $dump .= json_encode(DB::table($table)->get());
    }
    foreach (ownerSentinels() as $sentinel) {
        expect(stripos($dump, $sentinel))->toBeFalse("'{$sentinel}' leaked into the database");
    }

    $log = storage_path('logs/laravel.log');
    if (is_file($log)) {
        foreach (ownerSentinels() as $sentinel) {
            expect(stripos((string) file_get_contents($log), $sentinel))->toBeFalse("'{$sentinel}' leaked into the log");
        }
    }
});

test('queued page jobs carry no source data', function () {
    Queue::fake();
    importMorris();

    Queue::assertPushed(ImportParcelPage::class, function (ImportParcelPage $job) {
        $payload = serialize($job);
        foreach (ownerSentinels() as $sentinel) {
            if (stripos($payload, $sentinel) !== false) {
                return false;
            }
        }

        return $job->county === 'MORRIS' && $job->offset === 0;
    });
});

test('source HTTP errors never echo the response body', function () {
    Http::fake(['*' => Http::response('{"features":[{"properties":{"OWNER_NAME":"SENTINEL OWNER NAME"}}]}', 500)]);
    $source = new ArcGisNjParcelSource('https://example.test/FeatureServer/0');

    try {
        $source->count('MORRIS');
        $this->fail('expected exception');
    } catch (SourceRequestException $e) {
        expect($e->getMessage())->toContain('HTTP 500')->not->toContain('SENTINEL');
    }
});

test('a failing page marks only that county failed', function () {
    Queue::fake();
    $this->artisan('piu:import-nj-parcels', ['--county' => ['MORRIS', 'SUSSEX']])->assertSuccessful();

    $jobs = [];
    Queue::assertPushed(ImportParcelPage::class, function (ImportParcelPage $job) use (&$jobs) {
        $jobs[$job->county] = $job;

        return true;
    });

    // MORRIS: the source is down; the queue exhausts retries and calls failed().
    $this->app->instance(NjParcelSource::class, new class extends FakeNjParcelSource
    {
        public function page(string $county, int $offset, int $limit): SourcePage
        {
            throw new SourceRequestException('NJ parcel source page: HTTP 503');
        }
    });
    try {
        app()->call([$jobs['MORRIS'], 'handle']);
    } catch (SourceRequestException $e) {
        $jobs['MORRIS']->failed($e);
    }

    // SUSSEX: its own chain carries on.
    $this->app->instance(NjParcelSource::class, $this->source);
    app()->call([$jobs['SUSSEX'], 'handle']);
    app()->call([new FinalizeCountyImport($jobs['SUSSEX']->reportId), 'handle']);

    expect(County::where('name', 'MORRIS')->first()->status)->toBe(CountyStatus::Failed)
        ->and(County::where('name', 'MORRIS')->value('last_error'))->toContain('HTTP 503')
        ->and(County::where('name', 'SUSSEX')->first()->status)->toBe(CountyStatus::Loaded)
        ->and(ImportReport::where('county', 'MORRIS')->first()->status)->toBe(ImportStatus::Failed);
});

test('freshness check re-imports a republished county only', function () {
    importMorris();
    expect(ImportReport::count())->toBe(1);

    // Not republished: nothing queued.
    $this->source->pubMax = '2025-06-30';
    $this->artisan('piu:check-nj-parcels-freshness')->assertSuccessful();
    expect(ImportReport::count())->toBe(1);

    // Republished: one re-import, parcels updated in place.
    $this->source->pubMax = '2026-02-01';
    $this->artisan('piu:check-nj-parcels-freshness')->expectsOutputToContain('MORRIS: republished')->assertSuccessful();

    $report = ImportReport::where('trigger', 'freshness')->sole();
    expect($report->notes[0])->toContain('2026-02-01')
        ->and(Parcel::count())->toBe(5);
});

test('dry run writes nothing', function () {
    $this->artisan('piu:import-nj-parcels', ['--county' => ['MORRIS'], '--dry-run' => true])->assertSuccessful();

    expect(Parcel::count())->toBe(0)->and(ImportReport::count())->toBe(0);
});

test('unknown county is rejected', function () {
    $this->artisan('piu:import-nj-parcels', ['--county' => ['ATLANTIS']])->assertFailed();
});

test('centroid and site_zip are set as rows are written, with no follow-up UPDATE', function () {
    $updates = [];
    DB::listen(function ($q) use (&$updates) {
        if (preg_match('/^\s*update\s+"?parcels"?/i', $q->sql)) {
            $updates[] = $q->sql;
        }
    });

    importMorris();

    // The only parcels UPDATE allowed is the finalize fallback for rows still
    // missing a ZIP (here: the one parcel outside every NJ ZCTA).
    expect(array_filter($updates, fn ($sql) => ! str_contains($sql, 'site_zip IS NULL')))->toBe([])
        ->and(Parcel::whereNull('centroid')->count())->toBe(0)
        ->and(Parcel::where('prop_class', '2')->whereNull('site_zip')->count())->toBe(0);
});

test('parcels written before ZCTAs are loaded get their ZIP at finalize', function () {
    DB::table('zctas')->delete();
    importMorris();
    expect(Parcel::whereNotNull('site_zip')->count())->toBe(0);

    app(ZctaLoader::class)->fromFile(fixturePath('nj_zctas.geojson'));
    importMorris();

    expect(Parcel::where('prop_class', '2')->whereNull('site_zip')->count())->toBe(0);
});

test('piu:redecode-bldg-desc applies the current decoder without re-importing', function () {
    importMorris();
    $p = Parcel::where('pams_pin', '1427_11_1')->first();
    DB::table('parcels')->where('id', $p->id)->update(['bldg_desc_raw' => '2SF-L-2AG']);

    $this->artisan('piu:redecode-bldg-desc', ['--county' => 'morris'])->assertSuccessful();

    $p->refresh();
    expect($p->style)->toBe('Colonial')
        ->and($p->exterior)->toBe('Frame')
        ->and($p->garage_cars)->toBe(2)
        ->and($p->garage_type?->value)->toBe('attached');
});

test('summary shows a partial decode without inventing a style', function () {
    $p = new Parcel(['yr_constr' => 1962, 'stories' => 1.0, 'exterior' => 'Frame', 'style' => null, 'calc_acre' => 0.5, 'owner_occupied' => false]);

    expect($p->summary())->toBe('Built 1962 · 1-story frame · 0.50 ac · Not owner-occupied');
});

test('piu:redecode-bldg-desc runs one set-based UPDATE per county, not one per code', function () {
    importMorris();
    DB::table('parcels')->update(['style' => null, 'style_code' => null, 'exterior' => null, 'exterior_code' => null]);

    $updates = 0;
    DB::listen(function ($q) use (&$updates) {
        if (preg_match('/^\s*update\s+"?parcels"?/i', $q->sql)) {
            $updates++;
        }
    });

    $this->artisan('piu:redecode-bldg-desc')->assertSuccessful();

    expect($updates)->toBe(1) // fixture has a single county
        ->and(Parcel::where('pams_pin', '1427_10_1')->value('style'))->toBe('Colonial')
        ->and(Parcel::where('pams_pin', '1427_10_2')->value('exterior'))->toBe('Stone');
});

test('a page the source cannot serve whole is split into smaller fetches', function () {
    config(['piu.parcels.page_size' => 200]);
    $source = new class extends FakeNjParcelSource
    {
        /** @var list<int> */
        public array $calls = [];

        public function page(string $county, int $offset, int $limit): SourcePage
        {
            $this->calls[] = $limit;
            if ($limit > 50) {
                throw new SourceRequestException('NJ parcel source page: connection failed (cURL error 28: Operation timed out)');
            }

            return parent::page($county, $offset, $limit);
        }
    };
    // 120 fixture-shaped features with unique keys.
    $base = $source->features[0];
    $source->features = array_map(function ($i) use ($base) {
        $f = $base;
        $f['properties']['PCL_GUID'] = sprintf('{%08d-0000-0000-0000-000000000000}', $i);
        $f['properties']['PAMS_PIN'] = "1427_99_{$i}";

        return $f;
    }, range(1, 120));
    $this->app->instance(NjParcelSource::class, $source);

    importMorris();

    expect(Parcel::count())->toBe(120)
        ->and(ImportReport::sole()->status)->toBe(ImportStatus::Completed)
        ->and($source->calls)->toContain(200, 100, 50);
});

test('a page that fails even at the smallest split still fails the county', function () {
    $this->app->instance(NjParcelSource::class, new class extends FakeNjParcelSource
    {
        public function page(string $county, int $offset, int $limit): SourcePage
        {
            throw new SourceRequestException('NJ parcel source page: HTTP 503');
        }
    });

    expect(fn () => importMorris())->toThrow(SourceRequestException::class);
    expect(County::where('name', 'MORRIS')->first()->status)->toBe(CountyStatus::Failed);
});

test('pages request 6-decimal geometry and do not retry timeouts', function () {
    Http::fake([
        'example.test/FeatureServer/0/query*' => Http::response(file_get_contents(fixturePath('nj_parcels_morris_page.geojson'))),
        'example.test/FeatureServer/0*' => Http::response(['objectIdField' => 'OBJECTID']),
    ]);

    (new ArcGisNjParcelSource('https://example.test/FeatureServer/0'))->page('MORRIS', 0, 10);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/query') && $r['geometryPrecision'] == 6);
});
