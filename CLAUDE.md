# CLAUDE.md

Guidance for Claude Code (and humans) working in this repository.

## What PIU-mail is

A standalone, multi-tenant SaaS for home-service contractors. It finds
lookalike "cookie-cutter" homes near a job, groups them into a named **Target
Area**, and sends "Current Resident" postcards through Lob. First tenant: Pump
It Up Marketing (brand Sump Pump Gurus first). v1 is **New Jersey only**, on
free state parcel data.

Relay plan: 01 foundation + NJ parcel import (this) · 02 map + Target Areas ·
03 suppression + approval · 04 Lob sending · 05 Joby connector + attribution.

## Locked product decisions

- **Mail addressing:** "Current Resident" + street address. **No names are
  ever imported, stored, or printed.**
- **Scope:** New Jersey only. Source: NJOGIS statewide *Parcels and MOD-IV
  Composite*.
- **Tenancy: tenant = company, brands underneath.** `tenants` (the company) →
  `brands` (tenant-scoped). Target Areas and mailings (later relays) belong to
  a tenant and **carry a `brand_id`**. Users belong to a tenant (except Super
  Admins, who have none) and see all brands in their tenant.
- **Roles** (`App\Enums\Role`):
  - `super_admin` — PIU staff, all tenants, no tenant of their own.
  - `office` — Office/Marketing within a tenant: approves Target Areas and sends.
  - `tech` — creates Target Areas and enters field notes; **cannot approve or send**.
- **Frequency cap:** 90 days per address by default, overridable per Target
  Area. Built in relay 03.
  - *Pending owner confirmation:* the cap is enforced **per tenant across all
    brands** by default (a home never gets two of the same company's mailers
    in the window), with a per-tenant setting to switch to per-brand.
- **Target Area seeds:** a manually entered address ("Field find") or a Joby
  job (connector in relay 05).
- **Naming** (built in relay 02):
  - Target Area: `{SUBDIVISION}-{COUNTY}-{YYMM}-{SEQ}`, e.g. `HERITAGEHILLS-MORRIS-2611-01`.
  - Each mailing appends `-S{n}`.
  - **Confirmed:** subdivision is tech-entered; Office can edit it.
  - *Defaults awaiting confirmation:* unknown subdivision → main street name;
    YYMM = month the area was created; SEQ increments only when the same
    subdivision + county + month already exists; county = full uppercase name
    without "County".
- **Data retention:** keep **every** non-owner attribute the source provides.
  Owner identity fields are excluded (below).

## Parcel data rules (hard)

- **Owner fields are never persisted anywhere** — not in columns,
  `source_attributes`, logs, import reports, or queued/failed job payloads:
  `OWNER_NAME`, `ST_ADDRESS`, `CITY_STATE`, `ZIP_CODE`, `ZIP5`, `ZIP_PLUS4`
  (`App\Parcels\OwnerFields`). They are read in memory only, inside
  `ParcelMapper::map()`, to compute `owner_occupied`, then stripped. Page jobs
  carry only `(report id, county, offset)` and fetch rows inside `handle()`.
  Source HTTP errors never include the response body. Tests enforce all of this.
- **Parcels are global reference data**, not tenant-scoped.
- Typed columns for queried fields + the full non-owner record in
  `source_attributes jsonb`, so fields can be promoted later without re-importing.
- **Upsert key:** `pcl_guid` when present, else `(pams_pin, county,
  site_address)`. `pams_pin` is NOT unique in the source. Re-imports update
  rows in place: **parcel IDs are stable** (Target Areas reference them).
- **All property classes are imported**; every app query / default view uses
  `Parcel::residential()` (`prop_class = 2`, `dwell_units <=
  PARCELS_RESIDENTIAL_MAX_DWELL`, default 1). Fallback if statewide volume is
  impractical: `NJ_PARCELS_IMPORT_CLASSES=2`.
- **Assessed values (`land_val`, `imprvt_val`, `net_value`) are only
  comparable within a single municipality** — NJ towns reassess in different
  years. The UI must never compare them across municipalities.
- **Site ZIP** comes from a spatial join of the parcel centroid to Census ZCTA
  polygons (`zctas`), because MOD-IV ZIP/city fields are the *owner's mailing*
  address. Postal city comes from Lob verification (relay 04).
- `deed_date` source is **YYMMDD** (reverse of MOD-IV's usual order); century
  pivot = most recent date not in the future.
- `yr_constr` is null for 0 or anything outside 1700..current year.
- `BLDG_DESC` is decoded by `BldgDescDecoder` (`{stories}S{exterior}{style}{garage}`,
  longest-match exterior codes). **Never guess**: undecodable → decoded
  columns null, `bldg_desc_raw` kept. `BLDG_CLASS` stays raw (no official
  decode table located).

## Stack

| Concern  | Choice |
| -------- | ------ |
| Language | PHP 8.3 |
| Framework | Laravel 13 |
| Database | Postgres + **PostGIS** (Laravel Cloud Serverless Postgres / Neon) |
| Queue / cache | Redis |
| Keys | ULID primary keys everywhere |
| Admin | Filament at `/admin` (super_admin only in relay 01) |
| App UI | Inertia + React + TypeScript + Vite (placeholder page until relay 02) |
| Tests | Pest, against **real PostGIS** (no SQLite) |
| Style | Pint |
| Hosting | Laravel Cloud |

## Tenancy pattern

- `App\Models\Concerns\BelongsToTenant` adds `TenantScope` and fills
  `tenant_id` on create. Use it on every tenant-owned model.
- `App\Support\CurrentTenant` (request-scoped) holds the tenant. **Fail
  closed:** with no tenant and no bypass, scoped queries return nothing.
  Super admins bypass (`ResolveCurrentTenant` middleware). Cross-tenant code
  uses `CurrentTenant::withoutScope(fn () => ...)`.

## Commands

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # PostGIS extension, counties, PIU tenant + brands
php artisan piu:create-super-admin you@example.com "Your Name"

php artisan piu:check-postgis          # fails loudly if PostGIS is unavailable
php artisan piu:probe-nj-parcels       # Phase 0: live layer, field diff, owner-field counts, freshness
php artisan piu:load-zctas             # once: NJ ZCTA polygons from Census TIGERweb (or --file=)
php artisan piu:import-nj-parcels --county=MORRIS [--dry-run]
php artisan piu:import-nj-parcels      # all 21 counties, one job chain each
php artisan piu:check-nj-parcels-freshness   # scheduled weekly
php artisan piu:parcel-stats --county=MORRIS --sample-bldg=60 --statewide-rows=N

./vendor/bin/pest
./vendor/bin/pint
```

Tests need a Postgres with PostGIS: database `piu_mail_test` (see `phpunit.xml`).
CI runs them on the `postgis/postgis` service container.
