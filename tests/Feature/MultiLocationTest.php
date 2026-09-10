<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Location;
use Tests\TestCase;

/**
 * The task allows assuming a single location, but the schema carries location_id
 * everywhere. These tests prove that the assumption is a convenience rather than
 * something the invariants secretly rely on.
 */
final class MultiLocationTest extends TestCase
{
    private Location $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // A second location in a different timezone, so the tests also cover the
        // fact that opening hours are interpreted per location.
        $this->branch = Location::factory()->create([
            'name' => 'London branch',
            'slug' => 'london',
            'timezone' => 'Europe/London',
            'slot_duration_minutes' => 30,
        ]);

        foreach (range(1, 5) as $isoWeekday) {
            BusinessHour::query()->create([
                'location_id' => $this->branch->id,
                'day_of_week' => $isoWeekday,
                'opens_at' => '09:00:00',
                'closes_at' => '17:00:00',
            ]);
        }
    }

    public function test_each_location_reports_its_own_hours_in_its_own_timezone(): void
    {
        $this->getJson('/api/availability?date=2026-09-14')
            ->assertOk()
            ->assertJsonPath('meta.timezone', 'Europe/Warsaw')
            ->assertJsonPath('data.0.starts_at', '2026-09-14T09:00:00+02:00');

        $this->getJson("/api/availability?date=2026-09-14&location_id={$this->branch->id}")
            ->assertOk()
            ->assertJsonPath('meta.timezone', 'Europe/London')
            ->assertJsonPath('data.0.starts_at', '2026-09-14T09:00:00+01:00')
            ->assertJsonCount(16, 'data');
    }

    public function test_two_locations_can_hold_appointments_at_the_very_same_instant(): void
    {
        // 11:00 in Warsaw is 10:00 in London: one instant, a valid slot in both.
        $instant = '2026-09-14T11:00:00+02:00';

        $this->postJson('/api/appointments', [
            'starts_at' => $instant,
            'customer_email' => 'warsaw@example.test',
        ])->assertCreated();

        $this->postJson('/api/appointments', [
            'location_id' => $this->branch->id,
            'starts_at' => $instant,
            'customer_email' => 'london@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-09-14T10:00:00+01:00');

        // The unique index is scoped by location, so both rows are active.
        $this->assertSame(2, Appointment::query()->active()->count());
    }

    public function test_a_booking_in_one_location_does_not_block_the_other(): void
    {
        $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14T11:00:00+02:00',
            'customer_email' => 'warsaw@example.test',
        ])->assertCreated();

        $this->getJson('/api/availability?date=2026-09-14')->assertJsonCount(15, 'data');
        $this->getJson("/api/availability?date=2026-09-14&location_id={$this->branch->id}")
            ->assertJsonCount(16, 'data');
    }

    public function test_a_closing_day_only_closes_its_own_location(): void
    {
        $this->closeLocationOn('2026-09-14', 'Stocktaking');

        $this->getJson('/api/availability?date=2026-09-14')->assertJsonCount(0, 'data');
        $this->getJson("/api/availability?date=2026-09-14&location_id={$this->branch->id}")
            ->assertJsonCount(16, 'data');
    }

    public function test_the_appointment_list_is_scoped_to_one_location(): void
    {
        $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14T11:00:00+02:00',
            'customer_email' => 'warsaw@example.test',
        ])->assertCreated();

        $this->postJson('/api/appointments', [
            'location_id' => $this->branch->id,
            'starts_at' => '2026-09-14T11:00:00+02:00',
            'customer_email' => 'london@example.test',
        ])->assertCreated();

        $this->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.email', 'warsaw@example.test');

        $this->getJson("/api/appointments?location_id={$this->branch->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.email', 'london@example.test')
            ->assertJsonPath('data.0.location.slug', 'london');
    }

    public function test_the_locations_endpoint_lists_them_all(): void
    {
        $this->getJson('/api/locations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'main')
            ->assertJsonPath('data.1.timezone', 'Europe/London');
    }
}
