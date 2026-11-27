<?php

namespace App\Parcels;

use App\Enums\DwellingType;
use App\Enums\GarageType;

/**
 * A (possibly partial) BLDG_DESC decode. Each part is set only when the
 * source states it in a documented or owner-approved form:
 *  - stories + exterior from the {stories}S{exterior} code (or the reversed
 *    {exterior}{stories}S form, e.g. F2S);
 *  - style from a style code or a plain word (COLONIAL, CAPE COD...);
 *  - garage from {n}AG/{n}UG/AG/UG/G{n}/G;
 *  - dwelling type from plain words (SFD, 1 FAMILY, TOWNHOUSE, CONDO...).
 * unknownTokens counts parts left undecoded (the raw value is always kept).
 */
final readonly class DecodedBldgDesc
{
    public function __construct(
        public ?float $stories = null,
        public ?string $exteriorCode = null,
        public ?string $exterior = null,
        public ?string $styleCode = null,
        public ?string $style = null,
        public ?int $garageCars = null,
        public ?GarageType $garageType = null,
        public int $unknownTokens = 0,
        public ?DwellingType $dwellingType = null,
    ) {}

    public function complete(): bool
    {
        return $this->unknownTokens === 0;
    }

    /** "1.5-story stone Colonial, 2-car attached garage", "Cape Cod", "Condo" */
    public function display(): string
    {
        $parts = array_filter([$this->buildingDisplay(), $this->garageDisplay()]);

        if ($parts === [] && $this->dwellingType !== null) {
            return $this->dwellingType->label();
        }

        return implode(', ', $parts);
    }

    /** "2-story frame Colonial", "2-story frame", or just "Colonial". */
    public function buildingDisplay(): ?string
    {
        return self::building($this->stories, $this->exterior, $this->style);
    }

    public function garageDisplay(): ?string
    {
        return self::garage($this->garageCars, $this->garageType);
    }

    public static function building(?float $stories, ?string $exterior, ?string $style): ?string
    {
        if ($stories !== null && $exterior !== null) {
            return trim(sprintf('%s-story %s %s', self::formatStories($stories), strtolower($exterior), $style ?? ''));
        }

        return $style;
    }

    /** "2-car attached garage", "attached garage" (no count), "2-car garage" (attachment not stated). */
    public static function garage(?int $cars, ?GarageType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        // Attachment not stated (G / G2) => "2-car garage" / "garage".
        $kind = $type === GarageType::Unknown ? 'garage' : $type->value.' garage';

        return $cars === null ? $kind : sprintf('%d-car %s', $cars, $kind);
    }

    public static function formatStories(float $stories): string
    {
        return rtrim(rtrim(number_format($stories, 2, '.', ''), '0'), '.');
    }
}
