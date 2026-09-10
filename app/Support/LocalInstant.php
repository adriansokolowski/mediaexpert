<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class LocalInstant
{
    /**
     * Strict counterpart of CarbonImmutable::createFromFormat(), which reports a
     * nullable result. Callers here always pass an already validated string, so
     * a failure is a programming error rather than bad input.
     */
    public static function fromFormat(string $format, string $value, ?DateTimeZone $timezone = null): CarbonImmutable
    {
        $instant = CarbonImmutable::createFromFormat($format, $value, $timezone);

        if (! $instant instanceof CarbonImmutable) {
            throw new InvalidArgumentException("\"{$value}\" does not match the format \"{$format}\".");
        }

        return $instant;
    }

    /**
     * Reads a client-supplied datetime.
     *
     * "2026-09-14T09:00:00+02:00" and "2026-09-14T07:00:00Z" are honoured as
     * written; a value without an offset such as "2026-09-14 09:00" is read as
     * wall-clock time at the location. Everything is normalised to UTC, which
     * is how instants are persisted.
     */
    public static function parse(string $value, DateTimeZone $locationTimezone): CarbonImmutable
    {
        $value = trim($value);

        return self::carriesOffset($value)
            ? CarbonImmutable::parse($value)->utc()
            : CarbonImmutable::parse($value, $locationTimezone)->utc();
    }

    private static function carriesOffset(string $value): bool
    {
        return preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $value) === 1;
    }
}
