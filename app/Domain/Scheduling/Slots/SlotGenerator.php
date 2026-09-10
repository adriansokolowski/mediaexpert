<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Slots;

use App\Domain\Scheduling\ScheduleRepository;
use App\Models\Location;
use Carbon\CarbonImmutable;

/**
 * Turns opening hours into concrete slots.
 *
 * This is the single source of truth for what "a valid slot" means: both the
 * availability endpoint and the booking validation go through it, so the two
 * can never disagree.
 */
readonly class SlotGenerator
{
    public function __construct(private ScheduleRepository $schedule) {}

    /**
     * Every slot of one calendar day in the location's timezone, ignoring
     * existing bookings.
     *
     * @return list<Slot>
     */
    public function forDay(Location $location, CarbonImmutable $day): array
    {
        $localDay = $day->setTimezone($location->timezone())->startOfDay();

        if ($this->schedule->isClosedOn($location, $localDay)) {
            return [];
        }

        $duration = $location->slot_duration_minutes;

        if ($duration < 1) {
            return [];
        }

        $slots = [];

        foreach ($this->schedule->intervalsForWeekday($location, $localDay->dayOfWeekIso) as $interval) {
            [$opensAt, $closesAt] = $interval->on($localDay);

            // A slot is only offered when it fits inside the interval end to end,
            // so Saturday 10:00-14:20 stops after the 13:30-14:00 slot.
            for ($start = $opensAt; $start->addMinutes($duration) <= $closesAt; $start = $start->addMinutes($duration)) {
                $slots[] = new Slot($start, $start->addMinutes($duration));
            }
        }

        return $slots;
    }

    /**
     * The slot starting exactly at the given instant, or null when that instant
     * is not the start of a bookable slot.
     */
    public function slotStartingAt(Location $location, CarbonImmutable $startsAt): ?Slot
    {
        $wanted = $startsAt->getTimestamp();

        // A day holds a couple of dozen slots at most, so a scan is cheaper than
        // the extra branching an arithmetic check would need around DST changes.
        foreach ($this->forDay($location, $startsAt) as $slot) {
            if ($slot->key() === $wanted) {
                return $slot;
            }
        }

        return null;
    }
}
