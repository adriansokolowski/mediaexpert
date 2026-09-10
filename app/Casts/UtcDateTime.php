<?php

declare(strict_types=1);

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Persists an instant in UTC and reads it back in UTC.
 *
 * Eloquent formats dates on write without converting the timezone, so handing
 * it a Europe/Warsaw instant would store the wall-clock time and silently shift
 * the appointment by the current UTC offset. Normalising inside the cast keeps
 * that decision in one place instead of at every call site.
 *
 * Accepts a DateTimeInterface or a parsable string; anything else is rejected at
 * runtime, so the generic stays wide rather than promising more than Eloquent
 * actually guarantees when it hands values over.
 *
 * @implements CastsAttributes<CarbonImmutable, mixed>
 */
final class UtcDateTime implements CastsAttributes
{
    private const string STORAGE_FORMAT = 'Y-m-d H:i:s';

    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value, 'UTC');
        }

        throw new InvalidArgumentException("Cannot read {$key} as a datetime.");
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc()->format(self::STORAGE_FORMAT);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value)->utc()->format(self::STORAGE_FORMAT);
        }

        throw new InvalidArgumentException("Cannot store {$key} as a datetime.");
    }
}
