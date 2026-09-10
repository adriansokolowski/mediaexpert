<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use Tests\TestCase;

final class RescheduleAppointmentTest extends TestCase
{
    public function test_it_moves_an_appointment_to_another_slot(): void
    {
        $id = $this->book('2026-09-14 09:00', 'anna@example.test');

        $this->patchJson("/api/appointments/{$id}", ['starts_at' => '2026-09-15 11:30'])
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.starts_at', '2026-09-15T11:30:00+02:00')
            ->assertJsonPath('data.ends_at', '2026-09-15T12:00:00+02:00')
            ->assertJsonPath('data.status', 'booked');

        $this->assertSame(1, Appointment::query()->count());

        // The original slot is free again and the new one is taken.
        $this->getJson('/api/availability?date=2026-09-14')->assertJsonCount(16, 'data');
        $this->getJson('/api/availability?date=2026-09-15')->assertJsonCount(15, 'data');
    }

    public function test_it_refuses_to_move_onto_a_taken_slot(): void
    {
        $id = $this->book('2026-09-14 09:00', 'anna@example.test');
        $this->book('2026-09-14 10:00', 'piotr@example.test');

        $this->patchJson("/api/appointments/{$id}", ['starts_at' => '2026-09-14 10:00'])
            ->assertConflict()
            ->assertJsonPath('code', 'slot_already_booked');

        $this->assertSame(
            '2026-09-14 07:00:00',
            Appointment::query()->where('public_id', $id)->sole()->starts_at->utc()->toDateTimeString(),
        );
    }

    public function test_it_refuses_a_target_outside_the_schedule(): void
    {
        $id = $this->book('2026-09-14 09:00', 'anna@example.test');

        $this->patchJson("/api/appointments/{$id}", ['starts_at' => '2026-09-13 11:00'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'slot_outside_schedule');
    }

    public function test_moving_an_appointment_onto_its_own_slot_changes_nothing(): void
    {
        $id = $this->book('2026-09-14 09:00', 'anna@example.test');

        $this->patchJson("/api/appointments/{$id}", ['starts_at' => '2026-09-14 09:00'])
            ->assertOk()
            ->assertJsonPath('data.starts_at', '2026-09-14T09:00:00+02:00');
    }

    public function test_a_cancelled_appointment_cannot_be_moved(): void
    {
        $id = $this->book('2026-09-14 09:00', 'anna@example.test');
        $this->deleteJson("/api/appointments/{$id}")->assertOk();

        $this->patchJson("/api/appointments/{$id}", ['starts_at' => '2026-09-15 11:30'])
            ->assertConflict()
            ->assertJsonPath('code', 'appointment_not_active');
    }

    private function book(string $localStartsAt, string $email): string
    {
        return $this->postJson('/api/appointments', [
            'starts_at' => $localStartsAt,
            'customer_email' => $email,
        ])->assertCreated()->json('data.id');
    }
}
