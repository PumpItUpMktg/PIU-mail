<?php

use App\Enums\CountyStatus;
use App\Filament\Resources\Counties\Pages\ListCounties;
use App\Filament\Resources\Parcels\Pages\ListParcels;
use App\Models\County;
use App\Models\ImportReport;
use App\Models\Parcel;
use App\Models\User;
use App\Parcels\Source\NjParcelSource;
use Database\Seeders\CountySeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeNjParcelSource;

beforeEach(function () {
    $this->seed([CountySeeder::class, TenantSeeder::class]);
    $this->app->instance(NjParcelSource::class, new FakeNjParcelSource);
    $this->admin = User::factory()->superAdmin()->create();
});

test('super admin can open every admin screen', function () {
    $this->artisan('piu:import-nj-parcels', ['--county' => ['MORRIS']]);
    $parcel = Parcel::firstOrFail();
    $report = ImportReport::firstOrFail();

    $this->actingAs($this->admin);
    foreach (['/admin', '/admin/tenants', '/admin/brands', '/admin/users', '/admin/counties',
        '/admin/parcels', "/admin/parcels/{$parcel->id}", '/admin/import-reports', "/admin/import-reports/{$report->id}",
        '/admin/tenants/create', '/admin/users/create'] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get("/admin/parcels/{$parcel->id}")->assertSee($parcel->summary());
});

test('office and tech users cannot reach the admin panel', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/admin/parcels')->assertForbidden();
})->with(['tech', 'unverified']);

test('county Refresh and Retry failed queue an import', function () {
    Queue::fake();
    $morris = County::where('name', 'MORRIS')->first();
    $sussex = County::where('name', 'SUSSEX')->first();
    $sussex->update(['status' => CountyStatus::Failed, 'last_error' => 'boom']);

    $this->actingAs($this->admin);
    Livewire::test(ListCounties::class)
        ->callTableAction('refresh', $morris)
        ->assertTableActionHidden('retry', $morris)
        ->callTableAction('retry', $sussex);

    expect(ImportReport::where('county', 'MORRIS')->value('trigger'))->toBe('manual')
        ->and(ImportReport::where('county', 'SUSSEX')->value('trigger'))->toBe('retry')
        ->and($morris->fresh()->status)->toBe(CountyStatus::Queued);
});

test('the Inertia placeholder home page renders', function () {
    $this->withoutVite()->get('/')->assertOk()->assertSee('Home', false);
});

test('parcel browser defaults to residential and its filters narrow results', function () {
    $this->artisan('piu:import-nj-parcels', ['--county' => ['MORRIS']]);
    $this->actingAs($this->admin);

    $commercial = Parcel::where('prop_class', '4A')->first();
    $built1988 = Parcel::where('yr_constr', 1988)->first();
    $built1955 = Parcel::where('yr_constr', 1955)->first();

    Livewire::test(ListParcels::class)
        ->assertCanSeeTableRecords([$built1988, $built1955])
        ->assertCanNotSeeTableRecords([$commercial])
        ->filterTable('yr_constr', ['min' => 1980, 'max' => 2000])
        ->assertCanSeeTableRecords([$built1988])
        ->assertCanNotSeeTableRecords([$built1955])
        ->resetTableFilters()
        ->filterTable('residential', false)
        ->filterTable('prop_class', '4A')
        ->assertCanSeeTableRecords([$commercial])
        ->assertCanNotSeeTableRecords([$built1988]);
});
