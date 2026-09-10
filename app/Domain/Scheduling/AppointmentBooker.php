<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\Enums\AppointmentStatus;
use App\Domain\Scheduling\Exceptions\AppointmentNotActiveException;
use App\Domain\Scheduling\Exceptions\SlotAlreadyBookedException;
use App\Domain\Scheduling\Exceptions\SlotNotBookableException;
use App\Domain\Scheduling\Slots\Slot;
use App\Domain\Scheduling\Slots\SlotGenerator;
use App\Models\Appointment;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Every write to the appointment lifecycle goes through here.
 *
 * Slot validity is checked up front to give callers a helpful 422, but the
 * "one active appointment per slot" rule is *not* enforced by that check. It is
 * enforced by the unique index on (location_id, active_starts_at): the write is
 * attempted optimistically and a constraint violation is translated into a
 * conflict. Two concurrent requests therefore cannot both succeed, without
 * taking a lock and without depending on the transaction isolation level.
 */
readonly class AppointmentBooker
{
    public function __construct(private SlotGenerator $generator) {}

    public function book(
        Location $location,
        CarbonImmutable $startsAt,
        ?string $customerName,
        ?string $customerEmail,
    ): Appointment {
        $slot = $this->bookableSlot($location, $startsAt);

        $appointment = new Appointment([
            'location_id' => $location->id,
            'starts_at' => $slot->startsAt,
            'ends_at' => $slot->endsAt,
            'active_starts_at' => $slot->startsAt,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'status' => AppointmentStatus::Booked,
        ]);

        try {
            $appointment->save();
        } catch (UniqueConstraintViolationException $e) {
            throw new SlotAlreadyBookedException($slot->startsAt, $e);
        }

        $appointment->setRelation('location', $location);

        return $appointment;
    }

    /**
     * Releases the slot. Cancelling an already cancelled appointment is a no-op
     * so that a retried request cannot fail.
     */
    public function cancel(Appointment $appointment): Appointment
    {
        if (! $appointment->isActive()) {
            return $appointment;
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => CarbonImmutable::now(),
            // Frees the slot: NULLs do not collide in the unique index.
            'active_starts_at' => null,
        ])->save();

        return $appointment;
    }

    public function reschedule(Appointment $appointment, CarbonImmutable $startsAt): Appointment
    {
        if (! $appointment->isActive()) {
            throw new AppointmentNotActiveException;
        }

        $location = $appointment->location;
        $slot = $this->bookableSlot($location, $startsAt);

        if ($appointment->starts_at->equalTo($slot->startsAt)) {
            return $appointment;
        }

        $appointment->forceFill([
            'starts_at' => $slot->startsAt,
            'ends_at' => $slot->endsAt,
            'active_starts_at' => $slot->startsAt,
        ]);

        try {
            $appointment->save();
        } catch (UniqueConstraintViolationException $e) {
            throw new SlotAlreadyBookedException($slot->startsAt, $e);
        }

        return $appointment;
    }

    private function bookableSlot(Location $location, CarbonImmutable $startsAt): Slot
    {
        $slot = $this->generator->slotStartingAt($location, $startsAt);

        if ($slot === null) {
            throw SlotNotBookableException::outsideSchedule($startsAt);
        }

        if ($slot->startsAt <= CarbonImmutable::now()) {
            throw SlotNotBookableException::inThePast($startsAt);
        }

        return $slot;
    }
}
