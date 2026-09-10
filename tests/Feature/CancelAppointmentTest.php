<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Scheduling\Enums\AppointmentStatus;
use App\Models\Appointment;
use Tests\TestCase;

final class CancelAppointmentTest extends TestCase
{
    public function test_it_cancels_an_appointment_and_releases_the_slot(): void
    {
        $created = $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14 09:00',
            'customer_email' => 'anna@example.test',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->deleteJson("/api/appointments/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.starts_at', '2026-09-14T09:00:00+02:00');

        $appointment = Appointment::query()->sole();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertNotNull($appointment->cancelled_at);
        // Releasing the slot is exactly "active_starts_at becomes NULL"; the row
        // itself is kept for history.
        $this->assertNull($appointment->active_starts_at);

        $this->getJson('/api/availability?date=2026-09-14')
            ->assertOk()
            ->assertJsonCount(16, 'data');
    }

    public function test_the_released_slot_can_be_booked_by_somebody_else(): void
    {
        $payload = ['starts_at' => '2026-09-14 09:00', 'customer_email' => 'first@example.test'];

        $id = $this->postJson('/api/appointments', $payload)->assertCreated()->json('data.id');
        $this->deleteJson("/api/appointments/{$id}")->assertOk();

        $this->postJson('/api/appointments', [...$payload, 'customer_email' => 'second@example.test'])
            ->assertCreated()
            ->assertJsonPath('data.customer.email', 'second@example.test');

        $this->assertSame(2, Appointment::query()->count());
        $this->assertSame(1, Appointment::query()->active()->count());
    }

    public function test_cancelling_twice_is_a_no_op(): void
    {
        $id = $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14 09:00',
            'customer_email' => 'anna@example.test',
        ])->assertCreated()->json('data.id');

        $first = $this->deleteJson("/api/appointments/{$id}")->assertOk();
        $second = $this->deleteJson("/api/appointments/{$id}")->assertOk();

        $this->assertSame('cancelled', $second->json('data.status'));
        $this->assertSame($first->json('data.cancelled_at'), $second->json('data.cancelled_at'));
    }

    public function test_it_returns_404_for_an_unknown_appointment(): void
    {
        $this->deleteJson('/api/appointments/01JQZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    }
}
