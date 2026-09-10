<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClosingDay;
use Database\Seeders\ClosingDaySeeder;
use Tests\TestCase;

final class ClosingDaySeederTest extends TestCase
{
    public function test_it_seeds_fixed_and_movable_polish_holidays(): void
    {
        $this->seed(ClosingDaySeeder::class);

        $dates = ClosingDay::query()
            ->where('location_id', $this->location->id)
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $this->assertContains('2026-11-11', $dates);
        $this->assertContains('2026-12-25', $dates);
        // Easter Sunday 2026 falls on 5 April, so Easter Monday is the 6th and
        // Corpus Christi (Easter + 60 days) is 4 June.
        $this->assertContains('2026-04-06', $dates);
        $this->assertContains('2026-06-04', $dates);
    }

    public function test_a_seeded_holiday_has_no_availability(): void
    {
        $this->seed(ClosingDaySeeder::class);

        // 11 November 2026 is a Wednesday, so it would otherwise be a full day.
        $this->getJson('/api/availability?date=2026-11-11')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_can_be_run_repeatedly(): void
    {
        $this->seed(ClosingDaySeeder::class);
        $afterFirstRun = ClosingDay::query()->count();

        $this->seed(ClosingDaySeeder::class);

        $this->assertSame($afterFirstRun, ClosingDay::query()->count());
    }
}
