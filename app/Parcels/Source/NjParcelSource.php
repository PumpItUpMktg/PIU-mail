<?php

namespace App\Parcels\Source;

interface NjParcelSource
{
    /**
     * Layer metadata (name, fields, maxRecordCount, objectIdField, editingInfo).
     *
     * @return array<string, mixed>
     */
    public function layerInfo(): array;

    /** A version stamp for the layer (last edit date when published). */
    public function layerVersion(): ?string;

    public function count(string $county, ?string $extraWhere = null): int;

    /**
     * @param  string  $geometry  'full' (default), 'simplified' (server-side
     *                            generalized to ~5 m) or 'none' (attributes only)
     */
    public function page(string $county, int $offset, int $limit, string $geometry = 'full'): SourcePage;

    /**
     * Min/max of PCL_PBDATE and PCLLASTUPD, grouped by county.
     *
     * @return array<string, array{pub_min: mixed, pub_max: mixed, upd_min: mixed, upd_max: mixed, count: int}>
     */
    public function dateRangesByCounty(): array;
}
