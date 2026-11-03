<?php

namespace App\Parcels;

/**
 * A source feature mapped for persistence. Contains NO owner data: owner
 * fields are read in memory by ParcelMapper and discarded there.
 */
final readonly class MappedParcel
{
    /**
     * @param  array<string, mixed>  $columns  typed parcels columns
     * @param  array<string, mixed>  $sourceAttributes  verbatim non-owner attributes
     */
    public function __construct(
        public array $columns,
        public array $sourceAttributes,
        public ?string $geometryJson,
        public bool $bldgDescPresent,
        public bool $bldgDescDecoded,
    ) {}

    public function upsertKey(): string
    {
        $guid = $this->columns['pcl_guid'] ?? null;
        if ($guid !== null) {
            return 'guid:'.$guid;
        }

        return 'fallback:'.json_encode([
            $this->columns['pams_pin'] ?? null,
            $this->columns['county'] ?? null,
            $this->columns['site_address'] ?? null,
        ]);
    }
}
