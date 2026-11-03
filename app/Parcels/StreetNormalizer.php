<?php

namespace App\Parcels;

/**
 * Normalizes a street line so two spellings of the same address compare equal
 * ("12 North Main Street" == "12 N MAIN ST"). Used for owner_occupied and for
 * tidying PROP_LOC.
 */
final class StreetNormalizer
{
    /** @var array<string, string> */
    private const WORDS = [
        'STREET' => 'ST', 'STR' => 'ST',
        'AVENUE' => 'AVE', 'AV' => 'AVE',
        'ROAD' => 'RD',
        'DRIVE' => 'DR',
        'LANE' => 'LN',
        'COURT' => 'CT',
        'PLACE' => 'PL',
        'BOULEVARD' => 'BLVD',
        'TERRACE' => 'TER', 'TERR' => 'TER',
        'CIRCLE' => 'CIR',
        'PARKWAY' => 'PKWY',
        'HIGHWAY' => 'HWY',
        'TURNPIKE' => 'TPKE',
        'SQUARE' => 'SQ',
        'TRAIL' => 'TRL',
        'MOUNT' => 'MT',
        'NORTH' => 'N', 'SOUTH' => 'S', 'EAST' => 'E', 'WEST' => 'W',
        'NORTHEAST' => 'NE', 'NORTHWEST' => 'NW', 'SOUTHEAST' => 'SE', 'SOUTHWEST' => 'SW',
    ];

    /** Display-tidy: trimmed, single-spaced, uppercase. */
    public static function tidy(?string $street): ?string
    {
        $s = SourceValues::text($street);

        return $s === null ? null : strtoupper($s);
    }

    /** Comparison key: punctuation stripped, suffixes/directions abbreviated. */
    public static function key(?string $street): ?string
    {
        $s = self::tidy($street);
        if ($s === null) {
            return null;
        }

        $s = (string) preg_replace('/[^A-Z0-9 ]/', ' ', str_replace(['.', "'"], '', $s));
        $words = preg_split('/\s+/', trim($s)) ?: [];
        $words = array_map(fn (string $w) => self::WORDS[$w] ?? $w, array_filter($words, fn ($w) => $w !== ''));
        $key = implode(' ', $words);

        return $key === '' ? null : $key;
    }

    /**
     * Owner-occupied: true when the owner's mailing street equals the site
     * street, false when both are known and differ, null when either is
     * unavailable (e.g. redacted / blank in the public source).
     */
    public static function ownerOccupied(?string $ownerStreet, ?string $siteStreet): ?bool
    {
        $owner = self::key($ownerStreet);
        $site = self::key($siteStreet);

        if ($owner === null || $site === null) {
            return null;
        }

        return $owner === $site;
    }
}
