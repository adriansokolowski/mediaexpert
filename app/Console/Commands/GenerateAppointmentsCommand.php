<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Scheduling\Enums\AppointmentStatus;
use App\Domain\Scheduling\LocationResolver;
use App\Domain\Scheduling\ScheduleRepository;
use App\Domain\Scheduling\Slots\Slot;
use App\Domain\Scheduling\Slots\SlotGenerator;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills the appointments table with a realistic workload so that query plans
 * and index choices can be checked against a non-trivial data set.
 */
class GenerateAppointmentsCommand extends Command
{
    protected $signature = 'appointments:generate
        {--count=100000 : Number of appointments to insert}
        {--location= : Location id, defaults to the configured location}
        {--fill-rate=0.75 : Fraction of each day\'s slots to occupy}
        {--cancelled-rate=0.1 : Fraction of the generated rows that are cancelled}
        {--chunk=2000 : Rows per INSERT statement}';

    protected $description = 'Generate a large volume of appointments for performance testing';

    public function handle(
        LocationResolver $locations,
        SlotGenerator $generator,
        ScheduleRepository $schedule,
    ): int {
        $count = (int) $this->option('count');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $fillRate = (float) $this->option('fill-rate');
        $cancelledRate = (float) $this->option('cancelled-rate');

        if ($count < 1) {
            $this->components->error('--count must be at least 1.');

            return self::FAILURE;
        }

        $locationOption = $this->option('location');
        $location = $locations->resolve($locationOption === null ? null : (int) $locationOption);

        // Start the day after tomorrow so the generated data does not collide
        // with appointments created by hand while testing.
        $day = CarbonImmutable::now($location->timezone())->addDays(2)->startOfDay();

        // One day yields a couple of dozen slots at most; this is a generous
        // upper bound on how far ahead we need to walk.
        $schedule->preloadClosingDays($location, $day, $day->addDays((int) ceil($count / 10) + 30));

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $inserted = 0;
        $buffer = [];
        $table = DB::table('appointments');

        while ($inserted < $count) {
            foreach ($this->slotsToOccupy($generator, $location, $day, $fillRate) as $slot) {
                $buffer[] = $this->row($location, $slot, $cancelledRate);
                $inserted++;

                if (count($buffer) >= $chunkSize) {
                    $table->insertOrIgnore($buffer);
                    $bar->advance(count($buffer));
                    $buffer = [];
                }

                if ($inserted >= $count) {
                    break;
                }
            }

            $day = $day->addDay();
        }

        if ($buffer !== []) {
            $table->insertOrIgnore($buffer);
            $bar->advance(count($buffer));
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->info(sprintf('Generated %s appointments up to %s.', number_format($inserted), $day->toDateString()));

        return self::SUCCESS;
    }

    /** @return list<Slot> */
    private function slotsToOccupy(SlotGenerator $generator, Location $location, CarbonImmutable $day, float $fillRate): array
    {
        $slots = $generator->forDay($location, $day);

        if ($slots === [] || $fillRate >= 1.0) {
            return $slots;
        }

        return array_values(array_filter(
            $slots,
            static fn (): bool => mt_rand() / mt_getrandmax() < $fillRate,
        ));
    }

    /** @return array<string, mixed> */
    private function row(Location $location, Slot $slot, float $cancelledRate): array
    {
        $isCancelled = mt_rand() / mt_getrandmax() < $cancelledRate;
        $startsAtUtc = $slot->startsAt->utc()->toDateTimeString();
        $now = CarbonImmutable::now()->utc()->toDateTimeString();

        return [
            'public_id' => (string) Str::ulid(),
            'location_id' => $location->id,
            'starts_at' => $startsAtUtc,
            'ends_at' => $slot->endsAt->utc()->toDateTimeString(),
            // Cancelled rows release the slot by leaving this NULL.
            'active_starts_at' => $isCancelled ? null : $startsAtUtc,
            'customer_name' => 'Customer '.Str::random(8),
            'customer_email' => Str::random(10).'@example.test',
            'status' => $isCancelled ? AppointmentStatus::Cancelled->value : AppointmentStatus::Booked->value,
            'cancelled_at' => $isCancelled ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
