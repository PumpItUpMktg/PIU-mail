<?php

namespace App\Enums;

/**
 * What kind of dwelling BLDG_DESC states, when it says so in plain words
 * (SFD, 1 FAMILY, TOWNHOUSE, CONDO...). Separate from architectural style.
 * Owner-approved 2026-11-27.
 */
enum DwellingType: string
{
    case SingleFamily = 'single_family';
    case TwoFamily = 'two_family';
    case ThreeFamily = 'three_family';
    case Townhouse = 'townhouse';
    case Condo = 'condo';

    public function label(): string
    {
        return match ($this) {
            self::SingleFamily => 'Single-family',
            self::TwoFamily => 'Two-family',
            self::ThreeFamily => 'Three-family',
            self::Townhouse => 'Townhouse',
            self::Condo => 'Condo',
        };
    }
}
