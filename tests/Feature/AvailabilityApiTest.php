<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use Tests\TestCase;

final class AvailabilityApiTest extends TestCase
{
    public function test_it_lists_the_slots_of_a_weekday(): void
    {
        $response = $this->getJson('/api/availability?date=2026-09-14');

        $response->assertOk()
            ->assertJsonCount(16, 'data')
            ->assertJsonPath('data.0.starts_at', '2026-09-14T09:00:00+02:00')
            ->assertJsonPath('data.0.ends_at', '2026-09-14T09:30:00+02:00')
            ->assertJsonPath('meta.date', '2026-09-14')
            ->assertJsonPath('meta.timezone', 'Europe/Warsaw')
            ->assertJsonPath('meta.slot_duration_minutes', 30)
            ->assertJsonPath('meta.count', 16);
    }

    public function test_it_returns_an_empty_list_for_a_closed_day(): void
    {
        $this->getJson('/api/availability?date=2026-09-13')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.count', 0);
    }

    public function test_a_booked_slot_disappears_from_availability(): void
    {
        $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14 09:00',
            'customer_name' => 'Anna Kowalska',
        ])->assertCreated();

        $response = $this->getJson('/api/availability?date=2026-09-14');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('data.0.starts_at', '2026-09-14T09:30:00+02:00')
            ->assertJsonMissing(['starts_at' => '2026-09-14T09:00:00+02:00']);
    }

    public function test_a_cancelled_appointment_frees_the_slot_again(): void
    {
        Appointment::factory()
            ->for($this->location)
            ->startingAt($this->localTime('2026-09-14 09:00'))
            ->cancelled()
            ->create();

        $this->getJson('/api/availability?date=2026-09-14')
            ->assertOk()
            ->assertJsonCount(16, 'data')
            ->assertJsonPath('data.0.starts_at', '2026-09-14T09:00:00+02:00');
    }

    public function test_slots_that_have_already_started_are_not_offered(): void
    {
        // Frozen "now" is 2026-09-10 08:00, so the morning of that day is
        // partially gone once we move the clock past opening time.
        $this->travelTo($this->localTime('2026-09-10 10:15'));

        $response = $this->getJson('/api/availability?date=2026-09-10');

        $response->assertOk()
            ->assertJsonPath('data.0.starts_at', '2026-09-10T10:30:00+02:00')
            ->assertJsonCount(13, 'data');
    }

    public function test_it_validates_the_requested_date(): void
    {
        $this->getJson('/api/availability?date=14-09-2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('date');

        $this->getJson('/api/availability')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('date');
    }
}
