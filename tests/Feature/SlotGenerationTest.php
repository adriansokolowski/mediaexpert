<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Scheduling\Slots\Slot;
use App\Domain\Scheduling\Slots\SlotGenerator;
use Tests\TestCase;

/**
 * The opening-hours rules, exercised against the generator directly.
 */
final class SlotGenerationTest extends TestCase
{
    private SlotGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(SlotGenerator::class);
    }

    public function test_a_weekday_is_split_into_sixteen_slots(): void
    {
        // Monday, 09:00-17:00.
        $slots = $this->generator->forDay($this->location, $this->localTime('2026-09-14 00:00'));

        $this->assertCount(16, $slots);
        $this->assertSame('2026-09-14T09:00:00+02:00', $slots[0]->startsAt->toIso8601String());
        $this->assertSame('2026-09-14T16:30:00+02:00', $slots[15]->startsAt->toIso8601String());
        $this->assertSame('2026-09-14T17:00:00+02:00', $slots[15]->endsAt->toIso8601String());
    }

    public function test_saturday_stops_before_a_slot_would_overrun_closing_time(): void
    {
        // Saturday, 10:00-14:20: the 14:00-14:30 slot does not fit, so 13:30 is last.
        $slots = $this->generator->forDay($this->location, $this->localTime('2026-09-12 00:00'));

        $this->assertCount(8, $slots);
        $this->assertSame('2026-09-12T10:00:00+02:00', $slots[0]->startsAt->toIso8601String());
        $this->assertSame('2026-09-12T13:30:00+02:00', $slots[7]->startsAt->toIso8601String());
        $this->assertSame('2026-09-12T14:00:00+02:00', $slots[7]->endsAt->toIso8601String());

        $closesAt = $this->localTime('2026-09-12 14:20');

        foreach ($slots as $slot) {
            $this->assertTrue($slot->endsAt <= $closesAt, 'A slot may never end after closing time.');
        }
    }

    public function test_sunday_has_no_slots(): void
    {
        $this->assertSame([], $this->generator->forDay($this->location, $this->localTime('2026-09-13 00:00')));
    }

    public function test_a_closing_day_removes_every_slot(): void
    {
        $this->closeLocationOn('2026-09-14', 'Stocktaking');

        $this->assertSame([], $this->generator->forDay($this->location, $this->localTime('2026-09-14 00:00')));
    }

    public function test_it_recognises_the_start_of_a_slot(): void
    {
        $slot = $this->generator->slotStartingAt($this->location, $this->localTime('2026-09-14 09:30'));

        $this->assertInstanceOf(Slot::class, $slot);
        $this->assertSame('2026-09-14T10:00:00+02:00', $slot->endsAt->toIso8601String());
    }

    public function test_it_rejects_instants_that_do_not_start_a_slot(): void
    {
        $cases = [
            'before opening' => '2026-09-14 08:30',
            'off the 30 minute grid' => '2026-09-14 09:15',
            'exactly at closing time' => '2026-09-14 17:00',
            'after the last saturday slot' => '2026-09-12 14:00',
            'on a closed weekday' => '2026-09-13 11:00',
        ];

        foreach ($cases as $label => $localTime) {
            $this->assertNull(
                $this->generator->slotStartingAt($this->location, $this->localTime($localTime)),
                "Expected no slot {$label}."
            );
        }
    }
}
