<?php

use App\Models\Brand;
use App\Models\Tenant;
use App\Support\CurrentTenant;

beforeEach(function () {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    app(CurrentTenant::class)->withoutScope(function () {
        Brand::factory()->count(2)->create(['tenant_id' => $this->a->id]);
        Brand::factory()->count(3)->create(['tenant_id' => $this->b->id]);
    });
});

test('a tenant only sees its own brands', function () {
    app(CurrentTenant::class)->set($this->a->id);
    expect(Brand::count())->toBe(2)
        ->and(Brand::pluck('tenant_id')->unique()->all())->toBe([$this->a->id]);

    app(CurrentTenant::class)->set($this->b->id);
    expect(Brand::count())->toBe(3);
});

test('fails closed when no tenant is resolved', function () {
    app(CurrentTenant::class)->clear();

    expect(Brand::count())->toBe(0);
});

test('bypass (super admin) sees every tenant', function () {
    app(CurrentTenant::class)->bypass();

    expect(Brand::count())->toBe(5);
});

test('withoutScope restores the previous tenant', function () {
    app(CurrentTenant::class)->set($this->a->id);
    $all = app(CurrentTenant::class)->withoutScope(fn () => Brand::count());

    expect($all)->toBe(5)->and(Brand::count())->toBe(2);
});

test('tenant_id is auto-filled on create from the current tenant', function () {
    app(CurrentTenant::class)->set($this->b->id);
    $brand = Brand::create(['name' => 'New', 'slug' => 'new']);

    expect($brand->tenant_id)->toBe($this->b->id);
});

test('seeded tenant has its three brands', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $piu = Tenant::where('slug', 'pump-it-up-marketing')->first();
    app(CurrentTenant::class)->set($piu->id);

    expect(Brand::orderBy('name')->pluck('name')->all())
        ->toBe(['ClearScale Water', 'Sump Pump Gurus', 'Trusted Mold Testing']);
});
