<?php

namespace App\Parcels;

use Carbon\CarbonImmutable;

/**
 * Pure value sanitizers for MOD-IV / parcel source fields.
 */
final class SourceValues
{
    public const MIN_YEAR_BUILT = 1700;

    /**
     * YR_CONSTR: null for 0, blanks, and anything outside 1700..current year.
     */
    public static function yearBuilt(mixed $value, ?int $currentYear = null): ?int
    {
        $int = self::int($value);
        $currentYear ??= (int) date('Y');

        if ($int === null || $int < self::MIN_YEAR_BUILT || $int > $currentYear) {
            return null;
        }

        return $int;
    }

    /**
     * DEED_DATE is YYMMDD (the reverse of MOD-IV's MMDDYY). Century pivot: a
     * two-digit year resolves to the most recent date that is not after the
     * reference date (today), i.e. 20YY unless that lands in the future, then
     * 19YY. Returns null for anything that is not a real calendar date.
     */
    public static function deedDate(mixed $value, ?CarbonImmutable $reference = null): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);
        if ($digits === null || strlen($digits) !== 6) {
            return null;
        }

        $yy = (int) substr($digits, 0, 2);
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);

        $reference = ($reference ?? CarbonImmutable::now())->startOfDay();

        foreach ([2000 + $yy, 1900 + $yy] as $year) {
            if (! checkdate($mm, $dd, $year)) {
                continue;
            }
            $date = CarbonImmutable::create($year, $mm, $dd)->startOfDay();
            if ($date->lessThanOrEqualTo($reference)) {
                return $date;
            }
        }

        return null;
    }

    /**
     * ArcGIS date fields arrive as epoch milliseconds; text dates are accepted
     * in a few common formats. Returns null when unparseable.
     */
    public static function sourceDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d{10,13}$/', $value))) {
            $ms = (int) $value;
            if (strlen((string) abs($ms)) <= 10) {
                $ms *= 1000;
            }

            return CarbonImmutable::createFromTimestampMs($ms, 'UTC')->startOfDay();
        }

        $s = trim((string) $value);
        foreach (['Y-m-d', 'm/d/Y', 'Ymd', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'] as $format) {
            $d = CarbonImmutable::createFromFormat('!'.$format, $s, 'UTC');
            if ($d !== null && $d->format($format) === $s) {
                return $d->startOfDay();
            }
        }

        return null;
    }

    public static function int(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        $s = str_replace([',', '$'], '', trim((string) $value));
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (int) round((float) $s);
    }

    public static function decimal(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = str_replace([',', '$'], '', trim((string) $value));
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (string) (float) $s;
    }

    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $s === '' ? null : $s;
    }
}
