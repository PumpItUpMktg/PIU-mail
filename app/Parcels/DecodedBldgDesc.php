<?php

namespace App\Parcels;

use App\Enums\GarageType;

/**
 * A (possibly partial) BLDG_DESC decode. Stories and exterior are always
 * present; style and garage only when the source states them in a documented
 * form. unknownTokens counts parts that were left undecoded (kept in the raw
 * value), so "complete" means every part of the code was understood.
 */
final readonly class DecodedBldgDesc
{
    public function __construct(
        public float $stories,
        public string $exteriorCode,
        public string $exterior,
        public ?string $styleCode = null,
        public ?string $style = null,
        public ?int $garageCars = null,
        public ?GarageType $garageType = null,
        public int $unknownTokens = 0,
    ) {}

    public function complete(): bool
    {
        return $this->unknownTokens === 0;
    }

    /** "1.5-story stone Colonial, 2-car attached garage" */
    public function display(): string
    {
        $text = $this->buildingDisplay();

        if ($garage = $this->garageDisplay()) {
            $text .= ', '.$garage;
        }

        return $text;
    }

    /** "2-story frame Colonial", or "2-story frame" when no style is stated. */
    public function buildingDisplay(): string
    {
        return self::building($this->stories, $this->exterior, $this->style);
    }

    public function garageDisplay(): ?string
    {
        return self::garage($this->garageCars, $this->garageType);
    }

    public static function building(float $stories, string $exterior, ?string $style): string
    {
        return trim(sprintf('%s-story %s %s', self::formatStories($stories), strtolower($exterior), $style ?? ''));
    }

    /** "2-car attached garage", or "attached garage" when no count is stated. */
    public static function garage(?int $cars, ?GarageType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return $cars === null
            ? sprintf('%s garage', $type->value)
            : sprintf('%d-car %s garage', $cars, $type->value);
    }

    public static function formatStories(float $stories): string
    {
        return rtrim(rtrim(number_format($stories, 2, '.', ''), '0'), '.');
    }
}
