<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Scheduling\AppointmentBooker;
use App\Domain\Scheduling\Slots\Slot;
use App\Domain\Scheduling\Slots\SlotGenerator;
use App\Models\BusinessHour;
use App\Models\Location;
use App\Support\LocalInstant;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The seeded schedule closes on Sundays and both Polish DST switches happen in
 * the early hours of a Sunday, so the production configuration never meets a
 * transition. These tests cover the generator itself with a location that is
 * open across the switch, so the behaviour is pinned down rather than assumed.
 */
final class DaylightSavingTest extends TestCase
{
    private SlotGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(SlotGenerator::class);
    }

    public function test_the_spring_forward_hour_is_skipped_and_closing_time_holds(): void
    {
        // 28 March 2027: at 02:00 local time the clocks jump to 03:00.
        $location = $this->locationOpenOvernight();
        $slots = $this->generator->forDay($location, $this->dayIn($location, '2027-03-28'));

        // Midnight to 06:00 is only five real hours on this day.
        $this->assertCount(10, $slots);

        $starts = array_map(static fn (Slot $slot): string => $slot->startsAt->toIso8601String(), $slots);

        $this->assertSame([
            '2027-03-28T00:00:00+01:00',
            '2027-03-28T00:30:00+01:00',
            '2027-03-28T01:00:00+01:00',
            '2027-03-28T01:30:00+01:00',
            // 02:00 and 02:30 do not exist on this date.
            '2027-03-28T03:00:00+02:00',
            '2027-03-28T03:30:00+02:00',
            '2027-03-28T04:00:00+02:00',
            '2027-03-28T04:30:00+02:00',
            '2027-03-28T05:00:00+02:00',
            '2027-03-28T05:30:00+02:00',
        ], $starts);

        // The closing time is a wall-clock time and must not drift with the offset.
        $this->assertSame('2027-03-28T06:00:00+02:00', $slots[9]->endsAt->toIso8601String());
    }

    public function test_the_autumn_repeated_hour_produces_distinct_slots(): void
    {
        // 31 October 2027: 03:00 local time falls back to 02:00, so the day has
        // 25 hours and the 02:00 hour happens twice.
        $location = $this->locationOpenOvernight();
        $slots = $this->generator->forDay($location, $this->dayIn($location, '2027-10-31'));

        // Midnight to 06:00 is seven real hours on this day.
        $this->assertCount(14, $slots);

        $timestamps = array_map(static fn (Slot $slot): int => $slot->key(), $slots);

        $this->assertSame(
            $timestamps,
            array_values(array_unique($timestamps)),
            'The repeated hour must yield distinct instants, not duplicate bookings.'
        );

        $sorted = $timestamps;
        sort($sorted);
        $this->assertSame($sorted, $timestamps, 'Slots must be generated in chronological order.');

        // Every slot is exactly one duration long and they are contiguous.
        foreach ($slots as $index => $slot) {
            $this->assertSame(1800, $slot->endsAt->getTimestamp() - $slot->startsAt->getTimestamp());

            if ($index > 0) {
                $this->assertSame($slots[$index - 1]->endsAt->getTimestamp(), $slot->startsAt->getTimestamp());
            }
        }

        $this->assertSame('2027-10-31T06:00:00+01:00', $slots[13]->endsAt->toIso8601String());
    }

    public function test_a_booking_across_the_transition_round_trips_through_the_database(): void
    {
        $location = $this->locationOpenOvernight();

        // The first slot after the spring-forward gap.
        $startsAt = CarbonImmutable::parse('2027-03-28T03:00:00+02:00');

        $stored = app(AppointmentBooker::class)
            ->book($location, $startsAt, 'Anna Kowalska', null)
            ->refresh();

        $this->assertSame('2027-03-28 01:00:00', $stored->starts_at->utc()->toDateTimeString());
        $this->assertSame(
            '2027-03-28T03:00:00+02:00',
            $stored->starts_at->setTimezone($location->timezone())->toIso8601String(),
        );
    }

    private function locationOpenOvernight(): Location
    {
        $location = Location::factory()->create([
            'timezone' => 'Europe/Warsaw',
            'slot_duration_minutes' => 30,
        ]);

        BusinessHour::query()->create([
            'location_id' => $location->id,
            'day_of_week' => 7,
            'opens_at' => '00:00:00',
            'closes_at' => '06:00:00',
        ]);

        return $location;
    }

    private function dayIn(Location $location, string $date): CarbonImmutable
    {
        return LocalInstant::fromFormat('Y-m-d H:i:s', $date.' 12:00:00', $location->timezone());
    }
}
