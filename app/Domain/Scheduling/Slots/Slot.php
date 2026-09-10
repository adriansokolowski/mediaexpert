<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Slots;

use Carbon\CarbonImmutable;

/**
 * A bookable window. Both instants are absolute points in time; the timezone
 * they carry is the location's local timezone so they can be rendered directly.
 */
final readonly class Slot
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}

    /**
     * Stable key used to match a slot against booked rows without repeated
     * timezone conversions.
     */
    public function key(): int
    {
        return $this->startsAt->getTimestamp();
    }
}
