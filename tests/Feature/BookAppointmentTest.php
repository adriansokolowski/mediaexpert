<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Scheduling\Enums\AppointmentStatus;
use App\Models\Appointment;
use Tests\TestCase;

final class BookAppointmentTest extends TestCase
{
    public function test_it_books_a_free_slot(): void
    {
        $response = $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14 09:00',
            'customer_name' => 'Anna Kowalska',
            'customer_email' => 'anna@example.test',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-09-14T09:00:00+02:00')
            ->assertJsonPath('data.ends_at', '2026-09-14T09:30:00+02:00')
            ->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.customer.name', 'Anna Kowalska')
            ->assertJsonPath('data.cancelled_at', null);

        $appointment = Appointment::query()->sole();

        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame('2026-09-14 07:00:00', $appointment->starts_at->utc()->toDateTimeString());
        // The slot is held by the active-slot column, which is what the unique
        // index guards.
        $this->assertNotNull($appointment->active_starts_at);
        $this->assertTrue($appointment->active_starts_at->equalTo($appointment->starts_at));
    }

    public function test_it_honours_an_explicit_utc_offset_in_the_request(): void
    {
        $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14T07:00:00Z',
            'customer_email' => 'anna@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-09-14T09:00:00+02:00');
    }

    public function test_the_same_slot_cannot_be_booked_twice(): void
    {
        $payload = [
            'starts_at' => '2026-09-14 09:00',
            'customer_email' => 'first@example.test',
        ];

        $this->postJson('/api/appointments', $payload)->assertCreated();

        $this->postJson('/api/appointments', [...$payload, 'customer_email' => 'second@example.test'])
            ->assertConflict()
            ->assertJsonPath('code', 'slot_already_booked');

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_it_refuses_instants_the_schedule_does_not_offer(): void
    {
        $cases = [
            'before opening' => '2026-09-14 08:30',
            'after the last slot' => '2026-09-14 17:00',
            'off the 30 minute grid' => '2026-09-14 09:15',
            'on a sunday' => '2026-09-13 11:00',
            'overrunning saturday closing time' => '2026-09-12 14:00',
        ];

        foreach ($cases as $label => $startsAt) {
            $response = $this->postJson('/api/appointments', [
                'starts_at' => $startsAt,
                'customer_email' => 'anna@example.test',
            ]);

            $this->assertSame(422, $response->status(), "Expected a rejection: {$label}.");
            $this->assertSame('slot_outside_schedule', $response->json('code'), "Expected a rejection: {$label}.");
        }

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_it_refuses_a_slot_on_a_closing_day(): void
    {
        $this->closeLocationOn('2026-09-14', 'Public holiday');

        $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14 09:00',
            'customer_email' => 'anna@example.test',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'slot_outside_schedule');
    }

    public function test_it_refuses_a_slot_in_the_past(): void
    {
        // A perfectly valid Wednesday slot, but yesterday.
        $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-09 09:00',
            'customer_email' => 'anna@example.test',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'slot_in_the_past');
    }

    public function test_it_requires_a_name_or_an_email(): void
    {
        $this->postJson('/api/appointments', ['starts_at' => '2026-09-14 09:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_email']);

        $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14 09:00',
            'customer_name' => 'Anna Kowalska',
        ])->assertCreated();
    }

    public function test_it_validates_the_payload(): void
    {
        $this->postJson('/api/appointments', [
            'starts_at' => 'tomorrow-ish',
            'customer_email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at', 'customer_email']);
    }

    public function test_a_created_appointment_can_be_fetched_by_its_public_id(): void
    {
        $id = $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14 09:00',
            'customer_name' => 'Anna Kowalska',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/appointments/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.starts_at', '2026-09-14T09:00:00+02:00')
            ->assertJsonPath('data.status', 'booked');

        // The auto-increment key is never accepted as a public identifier.
        $this->getJson('/api/appointments/1')->assertNotFound();
    }

    public function test_it_rejects_an_unknown_location(): void
    {
        $this->postJson('/api/appointments', [
            'location_id' => 4242,
            'starts_at' => '2026-09-14 09:00',
            'customer_email' => 'anna@example.test',
        ])->assertNotFound();
    }
}
