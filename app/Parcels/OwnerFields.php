<?php

namespace App\Parcels;

/**
 * Owner identity fields from MOD-IV. These are NEVER persisted anywhere
 * (columns, source_attributes, logs, import reports, queued job payloads).
 * They may be read in memory only, e.g. to compute owner_occupied.
 */
final class OwnerFields
{
    public const KEYS = [
        'OWNER_NAME',
        'ST_ADDRESS',
        'CITY_STATE',
        'ZIP_CODE',
        'ZIP5',
        'ZIP_PLUS4',
    ];

    /**
     * Remove every owner field (case-insensitive key match).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function strip(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn ($key) => ! self::isOwnerField((string) $key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public static function isOwnerField(string $key): bool
    {
        return in_array(strtoupper(trim($key)), self::KEYS, true);
    }
}
