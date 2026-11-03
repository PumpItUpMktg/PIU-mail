<?php

namespace App\Parcels\Source;

final readonly class SourcePage
{
    /**
     * @param  list<array<string, mixed>>  $features  GeoJSON features (in memory only)
     */
    public function __construct(
        public array $features,
        public bool $exceededTransferLimit,
    ) {}
}
