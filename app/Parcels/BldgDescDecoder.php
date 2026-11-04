<?php

namespace App\Parcels;

use App\Enums\GarageType;

/**
 * Decodes the MOD-IV BLDG_DESC code: {stories}S{exterior}{style}{garage}.
 *
 *   1.5SSTL2AG  => 1.5 stories, ST stone, L Colonial, 2-car attached garage
 *
 * Real assessor data writes this loosely, so the decoder tolerates spaces,
 * dashes and slashes between parts ("1S F", "2SF-L-2AG", "2SF L AG") and
 * treats style and garage as optional. It still decodes ONLY documented codes:
 *
 *  - stories + exterior are required; without both the result is null.
 *  - style: a documented style code attached to the exterior or standing alone.
 *  - garage: {n}AG / {n}UG, or bare AG / UG (type known, count not stated).
 *  - anything else (G2, 2CG, 2BG, X, OB, POOL, COL, ...) is left undecoded
 *    and counted in unknownTokens; the raw value is always kept.
 *
 * Exterior codes overlap (S / SS / ST), so each candidate is tried and the
 * parse that leaves the fewest unknown parts wins, longest code on a tie.
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

    private const SEP = '[\s\-\/]';

    public function decode(?string $raw): ?DecodedBldgDesc
    {
        if ($raw === null) {
            return null;
        }

        $code = strtoupper(trim((string) preg_replace('/\s+/', ' ', $raw)));

        if (! preg_match('/^(\d+(?:\.\d+)?|\.\d+)\s*S(.*)$/', $code, $m)) {
            return null;
        }

        $stories = (float) $m[1];
        if ($stories <= 0 || $stories > 99) {
            return null;
        }

        $afterStories = (string) preg_replace('/^'.self::SEP.'+/', '', $m[2]);

        $exteriors = array_keys(self::EXTERIORS);
        usort($exteriors, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        $best = null;
        foreach ($exteriors as $ext) {
            if (! str_starts_with($afterStories, $ext)) {
                continue;
            }

            $rest = substr($afterStories, strlen($ext));
            $parsed = $this->parseRest($rest);
            if ($parsed === null) {
                continue;
            }

            // Fewest undecoded parts first, then most decoded parts; the
            // longest exterior code wins any remaining tie (loop order).
            $score = [$parsed['unknown'], -(($parsed['style'] !== null ? 1 : 0) + ($parsed['type'] !== null ? 1 : 0))];
            if ($best === null || $score < $best['score']) {
                $best = $parsed + ['ext' => $ext, 'score' => $score];
            }
        }

        if ($best === null) {
            return null;
        }

        return new DecodedBldgDesc(
            stories: $stories,
            exteriorCode: $best['ext'],
            exterior: self::EXTERIORS[$best['ext']],
            styleCode: $best['style'],
            style: $best['style'] !== null ? self::STYLES[$best['style']] : null,
            garageCars: $best['cars'],
            garageType: $best['type'],
            unknownTokens: $best['unknown'],
        );
    }

    /**
     * @return array{style: ?string, cars: ?int, type: ?GarageType, unknown: int}|null
     */
    private function parseRest(string $rest): ?array
    {
        $style = null;
        $cars = null;
        $type = null;
        $unknown = 0;

        // Style attached directly to the exterior: "SFL", "SFL2AG", "SFR".
        if ($rest !== '' && ! preg_match('/^'.self::SEP.'/', $rest)) {
            $first = $rest[0];
            $next = $rest[1] ?? '';
            $boundary = $next === '' || preg_match('/^'.self::SEP.'$/', $next) || ctype_digit($next);

            if (array_key_exists($first, self::STYLES) && $boundary && ! $this->isGarage($rest)) {
                $style = $first;
                $rest = substr($rest, 1);
            } elseif (! $this->isGarage($this->firstToken($rest)) && ! ctype_digit($first) && $first !== 'G') {
                return null;
            }
        }

        foreach (preg_split('/'.self::SEP.'+/', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (preg_match('/^(\d{1,2})?(AG|UG)$/', $token, $g)) {
                if ($type === null) {
                    $cars = $g[1] !== '' ? (int) $g[1] : null;
                    $type = $g[2] === 'AG' ? GarageType::Attached : GarageType::Unattached;

                    continue;
                }
            } elseif ($style === null && strlen($token) === 1 && array_key_exists($token, self::STYLES)) {
                $style = $token;

                continue;
            }

            $unknown++;
        }

        return ['style' => $style, 'cars' => $cars, 'type' => $type, 'unknown' => $unknown];
    }

    private function firstToken(string $s): string
    {
        return preg_split('/'.self::SEP.'+/', $s, 2)[0] ?? '';
    }

    private function isGarage(string $token): bool
    {
        return (bool) preg_match('/^(\d{1,2})?(AG|UG)$/', $token);
    }
}
