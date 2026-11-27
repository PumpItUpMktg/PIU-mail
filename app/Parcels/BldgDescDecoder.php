<?php

namespace App\Parcels;

use App\Enums\DwellingType;
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
 *  - garage: {n}AG / {n}UG, or bare AG / UG (type known, count not stated);
 *    G{n} / G = n-car garage / a garage, attachment unknown (owner-confirmed).
 *  - anything else (G2X, 2CG, 2BG, X, OB, POOL, COL, ...) is left undecoded
 *    and counted in unknownTokens; the raw value is always kept.
 *
 * Exterior codes overlap (S / SS / ST), so each candidate is tried and the
 * parse that leaves the fewest unknown parts wins, longest code on a tie.
 *
 * Owner-approved extensions (2026-11-27), still never guessing:
 *  A. plain-word styles: COLONIAL, RANCH, SPLIT LEVEL, CAPE COD, ... (STYLE_WORDS)
 *  B. reversed codes {exterior}{stories}S, e.g. F2S, B2S, F1S (strict form only)
 *  C. dwelling type from plain words: SFD, 1 FAMILY, TOWNHOUSE, CONDO, ... (DWELLING_WORDS)
 * Ambiguous values ('1 F', '2 F', 'BH', 'TH', 'COL', 'FR HSE') stay undecoded.
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

    /** Style labels for word-only styles that have no single-letter code. */
    public const EXTRA_STYLES = [
        'CC' => 'Cape Cod',
        'CT' => 'Contemporary',
        'VI' => 'Victorian',
        'BU' => 'Bungalow',
    ];

    /**
     * A. Plain-word styles (after phrase normalization, see PHRASES).
     *
     * @var array<string, string> word => style code (STYLES or EXTRA_STYLES)
     */
    public const STYLE_WORDS = [
        'COLONIAL' => 'L',
        'DUTCH_COLONIAL' => 'D',
        'RANCH' => 'R',
        'RANCHER' => 'R',
        'RAISED_RANCH' => 'Z',
        'SPLIT_LEVEL' => 'S',
        'BI_LEVEL' => '2',
        'TRI_LEVEL' => '3',
        'TUDOR' => 'E',
        'ENGLISH_TUDOR' => 'E',
        'DUPLEX' => 'X',
        'MOBILE_HOME' => 'M',
        'CAPE_COD' => 'CC',
        'CONTEMPORARY' => 'CT',
        'VICTORIAN' => 'VI',
        'BUNGALOW' => 'BU',
    ];

    /**
     * C. Plain-word dwelling types (after phrase normalization).
     *
     * @var array<string, DwellingType>
     */
    private const DWELLING_WORDS = [
        'SFD' => DwellingType::SingleFamily,
        'SINGLE_FAMILY' => DwellingType::SingleFamily,
        '1_FAMILY' => DwellingType::SingleFamily,
        '2_FAMILY' => DwellingType::TwoFamily,
        '3_FAMILY' => DwellingType::ThreeFamily,
        'TOWNHOUSE' => DwellingType::Townhouse,
        'TWNHSE' => DwellingType::Townhouse,
        'CONDO' => DwellingType::Condo,
        'CONDOMINIUM' => DwellingType::Condo,
    ];

    /**
     * Multi-word spellings folded into one token before parsing, so a
     * separator inside a phrase doesn't split it ("SPLIT-LEVEL", "1 FAM").
     *
     * @var array<string, string> regex => token
     */
    private const PHRASES = [
        '/\bDUTCH[\s\-]?COLONIAL\b/' => 'DUTCH_COLONIAL',
        '/\bRAISED[\s\-]?RANCH(?:ER)?\b/' => 'RAISED_RANCH',
        '/\bSPLIT[\s\-]?LEVEL\b/' => 'SPLIT_LEVEL',
        '/\bBI[\s\-]?LEVEL\b/' => 'BI_LEVEL',
        '/\bTRI[\s\-]?LEVEL\b/' => 'TRI_LEVEL',
        '/\bENGLISH[\s\-]?TUDOR\b/' => 'ENGLISH_TUDOR',
        '/\bMOBILE[\s\-]?HOME\b/' => 'MOBILE_HOME',
        '/\bCAPE[\s\-]?COD\b/' => 'CAPE_COD',
        '/\bSINGLE[\s\-]?FAMILY\b/' => 'SINGLE_FAMILY',
        '/\b(?:1|ONE)[\s\-]?FAM(?:ILY)?\b/' => '1_FAMILY',
        '/\b(?:2|TWO)[\s\-]?FAM(?:ILY)?\b/' => '2_FAMILY',
        '/\b(?:3|THREE)[\s\-]?FAM(?:ILY)?\b/' => '3_FAMILY',
        '/\bTOWN[\s\-]?(?:HOUSE|HOME)\b/' => 'TOWNHOUSE',
    ];

    /** A dwelling type is not set for these (OFFICE CONDO, COMMON AREA...). */
    private const NON_DWELLING = '/\b(?:OFFICE|COMMERCIAL|COMMON|RETAIL|INDUSTRIAL|BOAT)\b/';

    private const SEP = '[\s\-\/;,]';

    public function decode(?string $raw): ?DecodedBldgDesc
    {
        if ($raw === null) {
            return null;
        }

        $code = strtoupper(trim((string) preg_replace('/\s+/', ' ', $raw)));
        if ($code === '') {
            return null;
        }
        $code = (string) preg_replace(array_keys(self::PHRASES), array_values(self::PHRASES), $code);
        $dwellingAllowed = ! preg_match(self::NON_DWELLING, $code);

        $decoded = $this->decodeCode($code) ?? $this->decodeReversed($code) ?? $this->decodeWords($code);
        if ($decoded === null) {
            return null;
        }

        [$stories, $ext, $parsed] = $decoded;
        $dwelling = $dwellingAllowed ? $parsed['dwelling'] : null;

        if ($stories === null && $parsed['style'] === null && $dwelling === null) {
            return null;
        }

        return new DecodedBldgDesc(
            stories: $stories,
            exteriorCode: $ext,
            exterior: $ext !== null ? self::EXTERIORS[$ext] : null,
            styleCode: $parsed['style'],
            style: $parsed['style'] !== null ? self::styleLabel($parsed['style']) : null,
            garageCars: $parsed['cars'],
            garageType: $parsed['type'],
            unknownTokens: $parsed['unknown'],
            dwellingType: $dwelling,
        );
    }

    public static function styleLabel(string $code): ?string
    {
        return self::STYLES[$code] ?? self::EXTRA_STYLES[$code] ?? null;
    }

    /**
     * {stories}S{exterior}{style}{garage}, e.g. 1.5SSTL2AG, 2SF G2.
     *
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function decodeCode(string $code): ?array
    {
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

            $parsed = $this->parseRest(substr($afterStories, strlen($ext)));
            if ($parsed === null) {
                continue;
            }

            // Fewest undecoded parts first, then most decoded parts; the
            // longest exterior code wins any remaining tie (loop order).
            $score = [$parsed['unknown'], -(($parsed['style'] !== null ? 1 : 0) + ($parsed['type'] !== null ? 1 : 0))];
            if ($best === null || $score < $best['score']) {
                $best = ['parsed' => $parsed, 'ext' => $ext, 'score' => $score];
            }
        }

        return $best === null ? null : [$stories, $best['ext'], $best['parsed']];
    }

    /**
     * B. Reversed form {exterior}{stories}S, e.g. F2S, B2S, F1.5S, followed
     * by nothing or a separator. Anything glued on (F1SFG1, F1G1S) is not
     * this form and stays undecoded.
     *
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function decodeReversed(string $code): ?array
    {
        if (! preg_match('/^(AL|CB|RC|SS|ST|B|F|M|S|W)\s*(\d+(?:\.\d+)?|\.\d+)\s*S(?=$|'.self::SEP.')(.*)$/', $code, $m)) {
            return null;
        }

        $stories = (float) $m[2];
        if ($stories <= 0 || $stories > 99) {
            return null;
        }

        $parsed = $this->parseRest($m[3]);

        return $parsed === null ? null : [$stories, $m[1], $parsed];
    }

    /**
     * A + C. A value that is only words (COLONIAL, CAPE COD, SFD, CONDO).
     * Decodes when at least one word is a known style or dwelling word.
     *
     * @return array{0: null, 1: null, 2: array<string, mixed>}|null
     */
    private function decodeWords(string $code): ?array
    {
        $parsed = $this->parseRest(' '.$code, inCode: false);

        return $parsed !== null && ($parsed['style'] !== null || $parsed['dwelling'] !== null)
            ? [null, null, $parsed]
            : null;
    }

    /**
     * @return array{style: ?string, cars: ?int, type: ?GarageType, unknown: int, dwelling: ?DwellingType}|null
     */
    private function parseRest(string $rest, bool $inCode = true): ?array
    {
        $style = null;
        $cars = null;
        $type = null;
        $dwelling = null;
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
            } elseif ($type === null && preg_match('/^G(\d{1,2})?$/', $token, $g)) {
                // Owner-confirmed (2026-11-04): G{n} = n-car garage, G = a
                // garage; attachment not stated. G{n}X etc. stay undecoded.
                $cars = isset($g[1]) && $g[1] !== '' ? (int) $g[1] : null;
                $type = GarageType::Unknown;

                continue;
            } elseif ($inCode && $style === null && strlen($token) === 1 && array_key_exists($token, self::STYLES)) {
                // Single-letter style codes only count inside a building code;
                // in a plain-word value ('2 F', 'TYPE A') they'd be a guess.
                $style = $token;

                continue;
            } elseif (isset(self::STYLE_WORDS[$token])) {
                $style ??= self::STYLE_WORDS[$token];

                continue;
            } elseif (isset(self::DWELLING_WORDS[$token])) {
                // A townhouse that is also a condo is still a townhouse.
                if ($dwelling === null || $dwelling === DwellingType::Condo) {
                    $dwelling = self::DWELLING_WORDS[$token];
                }

                continue;
            }

            $unknown++;
        }

        return ['style' => $style, 'cars' => $cars, 'type' => $type, 'unknown' => $unknown, 'dwelling' => $dwelling];
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
