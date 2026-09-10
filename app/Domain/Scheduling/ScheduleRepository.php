<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\Slots\TimeInterval;
use App\Models\BusinessHour;
use App\Models\ClosingDay;
use App\Models\Location;
use Carbon\CarbonImmutable;

/**
 * Reads the opening rules of a location.
 *
 * Both lookups are memoised for the lifetime of the request: availability and
 * booking touch the same weekday and the same calendar day repeatedly, and the
 * underlying tables are tiny and change very rarely.
 */
class ScheduleRepository
{
    /** @var array<int, array<int, list<TimeInterval>>> location id => ISO weekday => intervals */
    private array $intervals = [];

    /** @var array<string, bool> "locationId:Y-m-d" => is closed */
    private array $closedDays = [];

    /**
     * Opening intervals for one ISO-8601 weekday (1 = Monday ... 7 = Sunday).
     * An empty list means the location is closed that weekday.
     *
     * @return list<TimeInterval>
     */
    public function intervalsForWeekday(Location $location, int $isoWeekday): array
    {
        return $this->intervalsByWeekday($location)[$isoWeekday] ?? [];
    }

    public function isClosedOn(Location $location, CarbonImmutable $localDay): bool
    {
        $date = $localDay->toDateString();

        // A plain equality match (rather than whereDate) so the lookup can use
        // the closing_days_location_date_unq index instead of scanning.
        return $this->closedDays[$location->id.':'.$date] ??= ClosingDay::query()
            ->where('location_id', $location->id)
            ->where('date', $date)
            ->exists();
    }

    /**
     * Resolves every closing day in the range with one query.
     *
     * Callers that walk many days (such as the data-generation command) would
     * otherwise issue one EXISTS per day.
     */
    public function preloadClosingDays(Location $location, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $closed = ClosingDay::query()
            ->where('location_id', $location->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date'])
            ->keyBy(static fn (ClosingDay $day): string => $day->date->toDateString());

        for ($day = $from->startOfDay(); $day <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();
            $this->closedDays[$location->id.':'.$date] = $closed->has($date);
        }
    }

    /** @return array<int, list<TimeInterval>> */
    private function intervalsByWeekday(Location $location): array
    {
        if (isset($this->intervals[$location->id])) {
            return $this->intervals[$location->id];
        }

        $byWeekday = [];

        /** @var BusinessHour $hour */
        foreach (BusinessHour::query()->where('location_id', $location->id)->get() as $hour) {
            $byWeekday[$hour->day_of_week][] = $hour->toInterval();
        }

        // Deterministic slot order when a weekday is split into several intervals.
        foreach ($byWeekday as &$intervals) {
            usort($intervals, static fn (TimeInterval $a, TimeInterval $b): int => $a->startMinuteOfDay <=> $b->startMinuteOfDay);
        }

        return $this->intervals[$location->id] = $byWeekday;
    }
}
