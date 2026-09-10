<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use Tests\TestCase;

final class ListAppointmentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeAppointment('2026-09-14 09:00', 'anna@example.test');
        $this->makeAppointment('2026-09-14 10:00', 'piotr@example.test');
        $this->makeAppointment('2026-09-15 09:00', 'anna@example.test');
        $this->makeAppointment('2026-09-16 09:00', 'anna@example.test', cancelled: true);
    }

    public function test_it_lists_appointments_ordered_by_start_time(): void
    {
        $response = $this->getJson('/api/appointments');

        $response->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.starts_at', '2026-09-14T09:00:00+02:00')
            ->assertJsonPath('data.3.starts_at', '2026-09-16T09:00:00+02:00');
    }

    public function test_it_filters_by_date_range(): void
    {
        $this->getJson('/api/appointments?from=2026-09-15')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // "to" is inclusive of the whole local day.
        $this->getJson('/api/appointments?from=2026-09-14&to=2026-09-14')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.starts_at', '2026-09-14T10:00:00+02:00');
    }

    public function test_it_filters_by_status_and_customer(): void
    {
        $this->getJson('/api/appointments?status=cancelled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'cancelled');

        $this->getJson('/api/appointments?customer_email=anna@example.test')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_it_paginates_with_a_cursor(): void
    {
        $firstPage = $this->getJson('/api/appointments?per_page=2')->assertOk();

        $firstPage->assertJsonCount(2, 'data');
        $cursor = $firstPage->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $secondPage = $this->getJson('/api/appointments?per_page=2&cursor='.urlencode($cursor))->assertOk();

        $secondPage->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.starts_at', '2026-09-15T09:00:00+02:00');

        $this->assertNull($secondPage->json('meta.next_cursor'));
    }

    public function test_it_rejects_unsupported_filters(): void
    {
        $this->getJson('/api/appointments?status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('status');

        $this->getJson('/api/appointments?per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('per_page');

        $this->getJson('/api/appointments?from=2026-09-15&to=2026-09-14')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('to');
    }

    private function makeAppointment(string $localStartsAt, string $email, bool $cancelled = false): void
    {
        $factory = Appointment::factory()
            ->for($this->location)
            ->startingAt($this->localTime($localStartsAt))
            ->state(['customer_email' => $email]);

        if ($cancelled) {
            $factory = $factory->cancelled();
        }

        $factory->create();
    }
}
