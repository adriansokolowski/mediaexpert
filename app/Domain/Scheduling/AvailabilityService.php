<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\Slots\Slot;
use App\Domain\Scheduling\Slots\SlotGenerator;
use App\Models\Location;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

readonly class AvailabilityService
{
    public function __construct(private SlotGenerator $generator) {}

    /**
     * Slots of the given local day that are still free and not in the past.
     *
     * Cost is one indexed range scan plus an in-memory difference, so it stays
     * flat no matter how many appointments the table holds in total.
     *
     * @return list<Slot>
     */
    public function forDay(Location $location, CarbonImmutable $day): array
    {
        $slots = $this->generator->forDay($location, $day);

        if ($slots === []) {
            return [];
        }

        $now = CarbonImmutable::now();

        $upcoming = array_values(array_filter(
            $slots,
            static fn (Slot $slot): bool => $slot->startsAt > $now,
        ));

        if ($upcoming === []) {
            return [];
        }

        $taken = $this->takenSlotKeys(
            $location,
            $upcoming[0]->startsAt,
            $upcoming[count($upcoming) - 1]->startsAt,
        );

        return array_values(array_filter(
            $upcoming,
            static fn (Slot $slot): bool => ! isset($taken[$slot->key()]),
        ));
    }

    /**
     * Start times of the active appointments in the window, as a set keyed by
     * Unix timestamp.
     *
     * Reads through the query builder rather than Eloquent: this runs on every
     * availability request and there is nothing to gain from hydrating models
     * for a single column.
     *
     * @return array<int, true>
     */
    private function takenSlotKeys(Location $location, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // active_starts_at is NULL for cancelled rows, so the range predicate on
        // the (location_id, active_starts_at) unique index already filters them
        // out and cancelled appointments never hide a free slot.
        $rows = DB::table('appointments')
            ->where('location_id', $location->id)
            ->whereBetween('active_starts_at', [
                $from->utc()->toDateTimeString(),
                $to->utc()->toDateTimeString(),
            ])
            ->pluck('active_starts_at');

        $taken = [];

        foreach ($rows as $value) {
            $taken[$this->toTimestamp($value)] = true;
        }

        return $taken;
    }

    /**
     * Drivers differ in what they hand back for a datetime column, so the value
     * is narrowed here rather than blindly cast.
     */
    private function toTimestamp(mixed $value): int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value, 'UTC')->getTimestamp();
        }

        throw new UnexpectedValueException('Unsupported datetime value returned by the database driver.');
    }
}
