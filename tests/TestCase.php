<?php

declare(strict_types=1);

namespace Tests;

use App\Models\ClosingDay;
use App\Models\Location;
use App\Support\LocalInstant;
use Carbon\CarbonImmutable;
use Database\Seeders\LocationSeeder;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Thursday, 10 September 2026, 08:00 local time.
     *
     * The clock is frozen so that "is this slot in the past?" and the weekday of
     * every fixture date are stable regardless of when the suite runs.
     */
    protected const string FROZEN_NOW = '2026-09-10 08:00:00';

    protected Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        // Opening hours only; closing days are added per test so that the
        // public-holiday calendar cannot silently affect other assertions.
        $this->seed(LocationSeeder::class);

        $this->location = Location::query()
            ->where('slug', (string) config('scheduling.default_location_slug'))
            ->sole();

        $this->travelTo(LocalInstant::fromFormat(
            'Y-m-d H:i:s',
            self::FROZEN_NOW,
            $this->location->timezone(),
        ));
    }

    /**
     * Builds an instant from wall-clock time at the location, e.g. "2026-09-14 09:00".
     */
    protected function localTime(string $dateAndTime): CarbonImmutable
    {
        return LocalInstant::fromFormat('Y-m-d H:i', $dateAndTime, $this->location->timezone())
            ->startOfMinute();
    }

    protected function timezone(): DateTimeZone
    {
        return $this->location->timezone();
    }

    protected function closeLocationOn(string $date, string $reason = 'Test closure'): ClosingDay
    {
        return ClosingDay::query()->create([
            'location_id' => $this->location->id,
            'date' => $date,
            'reason' => $reason,
        ]);
    }
}
