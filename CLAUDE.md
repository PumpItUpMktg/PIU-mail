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
- `BLDG_DESC` is decoded by `BldgDescDecoder` (`{stories}S{exterior}{style}{garage}`).
  Real assessor data is loose (`1SF`, `2SF G2`, `2SF-L-2AG`, `1S F G1`), so
  separators are tolerated and style/garage are optional; stories + exterior
  are required. **Never guess**: only documented codes decode. Garages:
  `{n}AG`/`{n}UG`/`AG`/`UG`, plus **owner-confirmed `G{n}` = n-car garage and
  `G` = a garage, attachment unknown** (`garage_type = unknown`). `G1X`, `2CG`,
  `2BG`, `OB`, `POOL`, `COL` etc. stay undecoded (counted as `unknownTokens`),
  and `bldg_desc_raw` is always kept. After changing the decoder run `piu:redecode-bldg-desc` (no
  re-import). `BLDG_CLASS` stays raw (no official decode table located).
- **Style is usually absent in Morris** (most homes are just `1SF`/`2SF`), so
  lookalike matching (relay 02) must not depend on style alone.
- `centroid` and `site_zip` are set by the `parcels_set_derived` trigger as a
  row is written. Never add follow-up `UPDATE parcels` passes: each one leaves
  a dead copy of every row (Morris measured 744 MB bloated vs 359 MB compact).
- `PCL_PBDATE` / `PCLLASTUPD` before 1900 are junk in the source (years like
  0144, 1899-12-30) and are stored as null.

## Live source facts (probed 2026-11-04)

- Layer `Parcels_Composite_NJ_WM/FeatureServer/0` (`Cad_parcel_mod4`),
  maxRecordCount 2000, objectIdField `OBJECTID`. Every expected field is
  present; extras: `OBJECTID`, `SHAPE__AREA`, `SHAPE__LENGTH`.
- `OWNER_NAME` is empty for every parcel (Daniel's Law). `ST_ADDRESS` is
  ~100% populated, so `owner_occupied` works (Morris: 137,246 true / 40,281
  false / 6 null).
- 3,481,240 parcels statewide; **403,260 have a blank `COUNTY` and none of
  them are class 2**, so the per-county import skipping them loses no homes.
- Each county has a single `PCL_PBDATE` (Union oldest: 2023-10-03).
- Morris: 177,533 parcels (152,289 class 2), 359 MB compact (~2.1 KB/row,
  geometry ~300 B, `source_attributes` ~960 B) → ~6.1 GB statewide.

## Laravel Cloud notes

- Queue and cache run on the **database** driver in production (no Redis
  attached). `retry_after` defaults to 1900s for database and redis queues so
  long import jobs are never re-run while still running.
- The queue worker needs `--timeout=1800`, and the app compute must not
  hibernate, or queued imports sit unprocessed. Its connection must be
  `database` (a worker left on `redis` silently processes nothing).
- The app runs in 512 MB. Parcel page responses are read as a stream and
  abandoned past `NJ_PARCELS_MAX_RESPONSE_MB` (16); the importer then splits the
  page (down to 50 records). Ocean's coastal parcels produced 132 MB pages that
  timed out, then OOM-killed the worker ("attempted too many times").

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

## Database guard

There is **no SQLite fallback**. `config/database.php` defaults to `pgsql`
with no localhost host default, and `App\Support\DatabaseGuard` runs whenever
a connection opens: a non-Postgres driver, or no `DB_HOST`/`DB_URL` (e.g. no
database attached in Laravel Cloud), stops with a plain-English
`DatabaseNotConfiguredException`. `piu:check-postgis` reports the same message.

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
php artisan piu:redecode-bldg-desc [--county=MORRIS]   # after decoder changes

./vendor/bin/pest
./vendor/bin/pint
```

Tests need a Postgres with PostGIS: database `piu_mail_test` (see `phpunit.xml`).
CI runs them on the `postgis/postgis` service container.
