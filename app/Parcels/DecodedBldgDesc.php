<?php

namespace App\Parcels;

use App\Enums\GarageType;

final readonly class DecodedBldgDesc
{
    public function __construct(
        public float $stories,
        public string $exteriorCode,
        public string $exterior,
        public string $styleCode,
        public string $style,
        public ?int $garageCars = null,
        public ?GarageType $garageType = null,
    ) {}

    /** "1.5-story stone Colonial, 2-car attached garage" */
    public function display(): string
    {
        $text = sprintf('%s-story %s %s', self::formatStories($this->stories), strtolower($this->exterior), $this->style);

        if ($garage = $this->garageDisplay()) {
            $text .= ', '.$garage;
        }

        return $text;
    }

    /** "2-story frame Colonial" (no garage) */
    public function buildingDisplay(): string
    {
        return sprintf('%s-story %s %s', self::formatStories($this->stories), strtolower($this->exterior), $this->style);
    }

    public function garageDisplay(): ?string
    {
        if ($this->garageCars === null || $this->garageType === null) {
            return null;
        }

        return sprintf('%d-car %s garage', $this->garageCars, $this->garageType->value);
    }

    public static function formatStories(float $stories): string
    {
        return rtrim(rtrim(number_format($stories, 2, '.', ''), '0'), '.');
    }
}
