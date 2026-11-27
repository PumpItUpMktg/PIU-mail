# Relay 01 close-out: scaffold, PostGIS, NJ parcel import

**Status: complete (2026-11-27).** All 21 New Jersey counties are loaded on
Laravel Cloud production. This file is the record of what shipped, the final
numbers, and what carries into relay 02.

## Final statewide numbers (production, 2026-11-27)

From `piu:parcel-stats --statewide-rows=3077980` after the last county (Ocean)
completed.

| Measure | Value |
|---|---|
| Parcels loaded | **3,077,980**, exactly NJOGIS's total for parcels with a county |
| Parcels skipped by design | 403,260 with a blank `COUNTY`, none of them class 2 |
| Residential (class 2) | **2,542,604** |
| Owner-occupied | 1,987,541 true / 1,090,128 false / 311 unknown |
| No site ZIP (outside every NJ ZCTA) | 258 |
| Storage (`parcels` table + indexes + TOAST) | **5.92 GB** (2.02 KB per row) |
| Parcels with a simplified or missing outline | 0 |

The storage figure matches the compacted Morris measurement (~2.1 KB per
row). The final `VACUUM FULL` wasn't confirmed before the stats ran, though,
so the true compacted size may be slightly lower.

Rows by property class: 1 = 156,601 · 15A = 5,025 · 15B = 1,919 ·
15C = 120,486 · 15D = 16,393 · 15E = 1,625 · 15F = 43,305 · **2 = 2,542,604** ·
3A = 2,975 · 3B = 31,037 · 4A = 119,325 · 4B = 16,433 · 4C = 16,981 ·
5A = 2,210 · 5B = 1,058 · 6A = 3.

### BLDG_DESC decode rates

| | Present | Stories + exterior | Style | Garage |
|---|---|---|---|---|
| All classes | 2,468,249 | 1,439,737 (58.3%) | 309,128 (12.5%) | 340,316 (13.8%) |
| Residential | 2,221,260 | 1,343,341 (**60.5%**) | 288,649 (**13.0%**) | 331,869 (14.9%) |

For comparison, Morris alone decodes stories + exterior for 82% of homes.
Many other counties write `BLDG_DESC` as plain words or in another order
(see "Decode gap" below).

## What shipped (PRs #1–#8)

- **#1:** Laravel 13 + PostGIS scaffold; tenants, brands, users and roles; the
  tenant scope; the Filament admin; the Inertia/React placeholder; CI on
  PostGIS; the NJ parcel importer, ZCTA site ZIPs, `BldgDescDecoder`, and owner
  fields never persisted.
- **#2:** no SQLite fallback (`DatabaseGuard`); a decoder for the loose
  real-world codes; a trigger-set centroid and ZIP (no row bloat); junk dates
  before 1900 stored as null.
- **#3:** one set-based `piu:redecode-bldg-desc` per county.
- **#4:** `G{n}` / `G` garages decoded (owner-confirmed).
- **#5:** 6-decimal geometry; page splitting.
- **#6:** a 16 MB cap on page responses, so huge pages split instead of the
  worker running out of memory.
- **#7:** single-parcel degraded outlines (`geometry_note`); nothing is skipped.
- **#8:** one source request per page job, with narrowing done across queued
  jobs. This finally cleared Ocean's 216,000 stretch with every parcel intact.

## Decode gap: A, B and C approved by the owner and built (2026-11-27)

Style is known for only **13% of homes**. The most common undecoded values
are plain words or other code orders, not the `{stories}S{exterior}…` format:

| Value | Rows | What it likely says | Proposed handling |
|---|---|---|---|
| `COLONIAL` | 10,900 | style | **A:** decode the style (needs approval) |
| `RANCH` | 8,746 | style = Rancher | **A** |
| `SPLIT LEVEL` | 4,014 | style | **A** |
| `CAPE COD` | 3,484 | style not in the spec table | **A:** new style label |
| `F2S`, `B2S`, `F1S` | 9,938 / 7,473 / 4,961 | exterior first, then stories (`F` frame, `B` brick) | **B:** decode the reversed order (needs approval) |
| `F1G1S`, `F1SFG1` | 5,060 / 4,245 | mixed order | **B**, only if a clear rule holds; otherwise leave |
| `SFD`, `1 FAMILY`, `1-FAM`, `2 FAMILY` | 16,051 / 5,607 / 7,734 / 7,682 | dwelling type, not style | **C:** a new `dwelling_type` field (not style) |
| `TOWNHOUSE`, `CONDO` | 5,039 / 22,537 | dwelling type | **C** |
| `FR HSE` | 7,241 | frame house (exterior only, no stories) | **C**, or exterior-only (needs approval) |
| `1 F`, `2 F`, `1F`, `F` | 7,538 / 8,467 / 3,865 / 4,427 | **ambiguous**: frame or family? | Leave undecoded (never guess) |
| `BH` | 13,349 | unknown | Leave undecoded unless the owner knows it |
| `VACANT LAND`, `OPEN SPACE`, `COMMON ELEMENTS`, `COMMON AREA` | — | not buildings | Leave undecoded (correct) |

**Approved and built on 2026-11-27.** Run `php artisan migrate --force`
(this adds `parcels.dwelling_type`), then `php artisan piu:redecode-bldg-desc`,
to apply A, B and C to the stored parcels. No re-import is needed. `FR HSE`,
`F1G1S` and `F1SFG1` stay undecoded: they weren't approved, or no clear rule
was found.

### Product implication for relay 02

Even with A–C, style will stay sparse in many towns. **Lookalike matching must
not depend on style.** Rank similarity on signals that are present for most
homes:
- year built (`yr_constr`);
- lot size (`calc_acre`);
- stories and exterior, where decoded;
- `dwell_units`;
- assessed value, compared **within the same municipality only**;
- sale and deed dates;
- spatial proximity to the seed address;
- optionally, outline footprint area and shape from `geom`.

Style should act as a booster where present, never a filter.

## Known open items

- **Pending owner confirmations** (unchanged, see `CLAUDE.md`):
  - the frequency cap applied per tenant across brands;
  - Target Area naming defaults.
- **Decode gap A/B/C:** awaiting an owner decision.
- **`BLDG_CLASS`** stays raw; no official decode table was located.
- **Redis** isn't attached in production; the queue and cache run on the
  database driver. That's fine at current volume.
