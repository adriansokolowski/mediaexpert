<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Scheduling\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addDay()->startOfHour();

        return [
            'location_id' => Location::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'active_starts_at' => $startsAt,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'status' => AppointmentStatus::Booked,
        ];
    }

    public function startingAt(CarbonImmutable $startsAt, int $durationMinutes = 30): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($durationMinutes),
            'active_starts_at' => $startsAt,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => CarbonImmutable::now(),
            'active_starts_at' => null,
        ]);
    }
}
