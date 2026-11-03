<?php

namespace App\Parcels;

use App\Enums\GarageType;

/**
 * Decodes the MOD-IV BLDG_DESC code: {stories}S{exterior}{style}{garage}.
 *
 *   1.5SSTL2AG  => 1.5 stories, ST stone, L Colonial, 2-car attached garage
 *
 * The codes overlap (S = stories suffix, Stucco, and Split level; ST/SS start
 * with S), so exterior codes are tried longest-first, falling back to a shorter
 * code only when the longer one leaves an unparseable remainder. Anything that
 * does not parse completely returns null: we never guess a decode.
 */
final class BldgDescDecoder
{
    /** @var array<string, string> */
    public const EXTERIORS = [
        'AL' => 'Aluminum siding',
        'B' => 'Brick',
        'CB' => 'Concrete block',
        'F' => 'Frame',
        'M' => 'Metal',
        'RC' => 'Reinforced concrete',
        'S' => 'Stucco',
        'SS' => 'Structural steel',
        'ST' => 'Stone',
        'W' => 'Wood',
    ];

    /** @var array<string, string> */
    public const STYLES = [
        'A' => 'Commercial',
        'S' => 'Split level',
        'B' => 'Industrial',
        'C' => 'Apartments',
        'D' => 'Dutch Colonial',
        'E' => 'English Tudor',
        'L' => 'Colonial',
        'M' => 'Mobile home',
        'R' => 'Rancher',
        'T' => 'Twin',
        'W' => 'Row home',
        'X' => 'Duplex',
        'Z' => 'Raised rancher',
        'O' => 'Other',
        '2' => 'Bi-level',
        '3' => 'Tri-level',
    ];

    public function decode(?string $raw): ?DecodedBldgDesc
    {
        if ($raw === null) {
            return null;
        }

        $code = strtoupper((string) preg_replace('/\s+/', '', $raw));

        if (! preg_match('/^(\d+(?:\.\d+)?|\.\d+)S(.+)$/', $code, $m)) {
            return null;
        }

        $stories = (float) $m[1];
        if ($stories <= 0 || $stories > 99) {
            return null;
        }

        $rest = $m[2];

        // Longest exterior code first; fall back only if the remainder fails.
        $exteriors = array_keys(self::EXTERIORS);
        usort($exteriors, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($exteriors as $ext) {
            if (! str_starts_with($rest, $ext)) {
                continue;
            }

            $tail = $this->parseStyleAndGarage(substr($rest, strlen($ext)));
            if ($tail === null) {
                continue;
            }

            [$styleCode, $cars, $type] = $tail;

            return new DecodedBldgDesc(
                stories: $stories,
                exteriorCode: $ext,
                exterior: self::EXTERIORS[$ext],
                styleCode: $styleCode,
                style: self::STYLES[$styleCode],
                garageCars: $cars,
                garageType: $type,
            );
        }

        return null;
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?GarageType}|null
     */
    private function parseStyleAndGarage(string $s): ?array
    {
        if ($s === '') {
            return null;
        }

        $style = $s[0];
        if (! array_key_exists($style, self::STYLES)) {
            return null;
        }

        $garage = substr($s, 1);
        if ($garage === '') {
            return [$style, null, null];
        }

        if (! preg_match('/^(\d{1,2})(AG|UG)$/', $garage, $g)) {
            return null;
        }

        return [$style, (int) $g[1], $g[2] === 'AG' ? GarageType::Attached : GarageType::Unattached];
    }
}
