<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Scheduling\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Two parallel requests can both pass the "is this slot free?" check, so the
 * rule has to be enforced by the database rather than by application code.
 */
final class ConcurrentBookingTest extends TestCase
{
    public function test_the_database_refuses_a_second_active_row_for_the_same_slot(): void
    {
        $startsAt = $this->localTime('2026-09-14 09:00');

        Appointment::factory()->for($this->location)->startingAt($startsAt)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        // Writing straight to the table bypasses every application-level check,
        // which is what a competing process effectively does.
        DB::table('appointments')->insert($this->rawRow($startsAt, active: true));
    }

    public function test_the_loser_of_a_race_receives_a_conflict_instead_of_a_duplicate(): void
    {
        $startsAt = $this->localTime('2026-09-14 09:00');
        $competitorInserted = false;

        // Slips a competing appointment in after validation has already seen the
        // slot as free, but before this request manages to write its own row.
        Appointment::creating(function () use ($startsAt, &$competitorInserted): void {
            if ($competitorInserted) {
                return;
            }

            $competitorInserted = true;
            DB::table('appointments')->insert($this->rawRow($startsAt, active: true));
        });

        $this->postJson('/api/appointments', [
            'starts_at' => '2026-09-14 09:00',
            'customer_email' => 'late@example.test',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'slot_already_booked');

        $this->assertTrue($competitorInserted);
        $this->assertSame(1, Appointment::query()->active()->count());
        $this->assertNull(Appointment::query()->where('customer_email', 'late@example.test')->first());
    }

    public function test_cancelled_rows_may_pile_up_on_the_same_slot(): void
    {
        $startsAt = $this->localTime('2026-09-14 09:00');

        // active_starts_at is NULL for all three, and NULLs do not collide in a
        // unique index, so the history of a busy slot is never lost.
        DB::table('appointments')->insert([
            $this->rawRow($startsAt, active: false),
            $this->rawRow($startsAt, active: false),
            $this->rawRow($startsAt, active: false),
        ]);

        $this->assertSame(3, Appointment::query()->count());

        $this->getJson('/api/availability?date=2026-09-14')->assertJsonCount(16, 'data');
    }

    /** @return array<string, mixed> */
    private function rawRow(CarbonImmutable $startsAt, bool $active): array
    {
        $startsAtUtc = $startsAt->utc()->toDateTimeString();
        $now = CarbonImmutable::now()->utc()->toDateTimeString();

        return [
            'public_id' => (string) Str::ulid(),
            'location_id' => $this->location->id,
            'starts_at' => $startsAtUtc,
            'ends_at' => $startsAt->addMinutes(30)->utc()->toDateTimeString(),
            'active_starts_at' => $active ? $startsAtUtc : null,
            'customer_name' => 'Competitor',
            'customer_email' => 'competitor@example.test',
            'status' => $active ? AppointmentStatus::Booked->value : AppointmentStatus::Cancelled->value,
            'cancelled_at' => $active ? null : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
