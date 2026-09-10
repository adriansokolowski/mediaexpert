<?php

declare(strict_types=1);

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A calendar date with no time and no timezone, stored as "Y-m-d".
 *
 * Eloquent's built-in date cast writes a full "Y-m-d H:i:s" string, which does
 * not round-trip against a DATE column and breaks plain `where('date', ...)`
 * lookups because the query builder does not apply casts to its bindings.
 *
 * Accepts a DateTimeInterface or a parsable string; anything else is rejected at
 * runtime, so the generic stays wide rather than promising more than Eloquent
 * actually guarantees when it hands values over.
 *
 * @implements CastsAttributes<CarbonImmutable, mixed>
 */
final class CalendarDate implements CastsAttributes
{
    private const string STORAGE_FORMAT = 'Y-m-d';

    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value)->startOfDay();
        }

        throw new InvalidArgumentException("Cannot read {$key} as a date.");
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->format(self::STORAGE_FORMAT);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value)->format(self::STORAGE_FORMAT);
        }

        throw new InvalidArgumentException("Cannot store {$key} as a date.");
    }
}
